<?php
/**
 * Stock Price Sender Service
 * Handles sending stock and price updates to child shops
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class StockPriceSenderService
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Ensure SPSRemoteShop class is loaded
        require_once(_PS_MODULE_DIR_ . 'stockpricesync/classes/SPSRemoteShop.php');
    }

    /**
     * Process a stock update for a product
     */
    public function processStockUpdate($product_reference, $combination_reference, $quantity)
    {
        // Get shops that need stock sync
        $shops = SPSRemoteShop::getActiveShopsWithStockSync();
        
        if (empty($shops)) {
            return false; // No shops to sync
        }
        
        // Check if we're in batch mode or real-time
        $batch_size = (int)Configuration::get('STOCKPRICESYNC_BATCH_SIZE');
        $use_batch = count($shops) > $batch_size;
        
        if ($use_batch) {
            // Add to queue for batch processing
            $result = $this->addToQueue($product_reference, $combination_reference, 'stock', $quantity);
            
            // Si se han añadido elementos a la cola, procesar uno o más elementos inmediatamente
            if ($result) {
                // Procesar algunos elementos de la cola después de añadir uno nuevo
                $this->processQueue(5); // Procesar 5 elementos para no sobrecargar
            }
            
            return ['success' => true, 'queued' => true];
        } else {
            // Send directly to each shop
            $results = [];
            
            foreach ($shops as $shop) {
                $result = $this->sendStockToShop(
                    $shop,
                    $product_reference,
                    $combination_reference,
                    $quantity
                );
                
                $results[$shop['id_shop_remote']] = $result;
            }
            
            return ['success' => true, 'results' => $results];
        }
    }

    /**
     * Process a price update for a product
     */
    public function processPriceUpdate($product, $id_product_attribute = 0)
    {
        // Get shops that need price sync
        $shops = SPSRemoteShop::getActiveShopsWithPriceSync();

        if (empty($shops)) {
            return false; // No shops to sync
        }

        // Get product reference
        $product_reference = $product->reference;

        // Get combination reference if needed
        $combination_reference = null;
        $price_impact = 0;

        if ($id_product_attribute > 0) {
            $combination = new Combination($id_product_attribute);
            if (Validate::isLoadedObject($combination) && !empty($combination->reference)) {
                $combination_reference = $combination->reference;
                // Get combination price impact
                $price_impact = (float)$combination->price;
            }
        }

        // Get product base price
        $base_price = (float)$product->price;

        // Check if we're in batch mode or real-time
        $batch_size = (int)Configuration::get('STOCKPRICESYNC_BATCH_SIZE');
        $use_batch = count($shops) > $batch_size;

        if ($use_batch) {
            // Add to queue for batch processing
            // Store base_price and price_impact separately so percentage can be applied correctly
            $result = $this->addToQueue($product_reference, $combination_reference, 'price', null, $base_price, $price_impact);

            // Si se han añadido elementos a la cola, procesar uno o más elementos inmediatamente
            if ($result) {
                // Procesar algunos elementos de la cola después de añadir uno nuevo
                $this->processQueue(5); // Procesar 5 elementos para no sobrecargar
            }

            return ['success' => true, 'queued' => true];
        } else {
            // Send directly to each shop
            // Apply percentage correctly: (base * (1 + %)) + impact
            $results = [];

            foreach ($shops as $shop) {
                $adjusted_base = $base_price;

                // Apply percentage only to base price
                if (isset($shop['price_percentage']) && $shop['price_percentage'] != 0) {
                    $adjusted_base = $base_price * (1 + ($shop['price_percentage'] / 100));
                }

                // Final price = adjusted base + impact
                $final_price = $adjusted_base + $price_impact;

                $result = $this->sendPriceToShop(
                    $shop,
                    $product_reference,
                    $combination_reference,
                    $final_price
                );

                $results[$shop['id_shop_remote']] = $result;
            }

            return ['success' => true, 'results' => $results];
        }
    }

    /**
     * Add an update to the queue
     */
    public function addToQueue($product_reference, $combination_reference, $sync_type, $quantity = null, $price = null, $price_impact = null)
    {
        try {
            // Check if already in queue
            $sql = '
                SELECT id_queue 
                FROM `'._DB_PREFIX_.'stockpricesync_queue` 
                WHERE product_reference = "'.pSQL($product_reference).'"';
            
            if ($combination_reference) {
                $sql .= ' AND combination_reference = "'.pSQL($combination_reference).'"';
            } else {
                $sql .= ' AND combination_reference IS NULL';
            }
            
            $sql .= ' AND status IN ("pending", "processing")';
            
            $existing_id = Db::getInstance()->getValue($sql);
            
            // Current timestamp
            $now = date('Y-m-d H:i:s');
            
            if ($existing_id) {
                // Update existing queue item
                $data = [
                    'sync_type' => $sync_type == 'both' ? 'both' : (($sync_type == 'price' || $sync_type == 'stock') ? $sync_type : 'both'),
                    'date_upd' => $now
                ];

                if ($quantity !== null) {
                    $data['quantity'] = (int)$quantity;
                }

                if ($price !== null) {
                    $data['price'] = (float)$price;
                }

                if ($price_impact !== null) {
                    $data['price_impact'] = (float)$price_impact;
                }

                return Db::getInstance()->update(
                    'stockpricesync_queue',
                    $data,
                    'id_queue = '.(int)$existing_id
                );
            } else {
                // Insert new queue item
                return Db::getInstance()->insert(
                    'stockpricesync_queue',
                    [
                        'product_reference' => pSQL($product_reference),
                        'combination_reference' => $combination_reference ? pSQL($combination_reference) : null,
                        'sync_type' => $sync_type == 'both' ? 'both' : (($sync_type == 'price' || $sync_type == 'stock') ? $sync_type : 'both'),
                        'quantity' => $quantity !== null ? (int)$quantity : null,
                        'price' => $price !== null ? (float)$price : null,
                        'price_impact' => $price_impact !== null ? (float)$price_impact : null,
                        'priority' => 1,
                        'status' => 'pending',
                        'attempts' => 0,
                        'date_add' => $now,
                        'date_upd' => $now
                    ]
                );
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Send stock update to a specific shop
     */
    private function sendStockToShop($shop, $product_reference, $combination_reference, $quantity)
    {
        try {
            // Prepare data
            $data = [
                'shop_name' => Configuration::get('PS_SHOP_NAME'),
                'product_reference' => $product_reference,
                'combination_reference' => $combination_reference,
                'quantity' => (int)$quantity,
                'update_type' => 'stock'
            ];
            
            // Send to API endpoint
            $url = rtrim($shop['url'], '/') . '/modules/stockpricesync/api/update.php';
            $result = $this->sendRequest($data, $url, $shop['api_key']);
            
            // Log the sync
            $module = Module::getInstanceByName('stockpricesync');
            $module->logSync(
                $shop['id_shop_remote'],
                'stock',
                $product_reference,
                $combination_reference,
                null, // old quantity unknown
                $quantity,
                null,
                null,
                $result['success'] ? 1 : 0,
                $result['message']
            );
            
            return $result;
        } catch (Exception $e) {
            // Log the error
            $module = Module::getInstanceByName('stockpricesync');
            $module->logSync(
                $shop['id_shop_remote'],
                'stock',
                $product_reference,
                $combination_reference,
                null,
                $quantity,
                null,
                null,
                0,
                'Exception: ' . $e->getMessage()
            );
            
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send price update to a specific shop
     */
    private function sendPriceToShop($shop, $product_reference, $combination_reference, $price)
    {
        try {
            // Prepare data
            $data = [
                'shop_name' => Configuration::get('PS_SHOP_NAME'),
                'product_reference' => $product_reference,
                'combination_reference' => $combination_reference,
                'price' => (float)$price,
                'update_type' => 'price'
            ];
            
            // Send to API endpoint
            $url = rtrim($shop['url'], '/') . '/modules/stockpricesync/api/update.php';
            $result = $this->sendRequest($data, $url, $shop['api_key']);
            
            // Log the sync
            $module = Module::getInstanceByName('stockpricesync');
            $module->logSync(
                $shop['id_shop_remote'],
                'price',
                $product_reference,
                $combination_reference,
                null,
                null,
                null,
                $price,
                $result['success'] ? 1 : 0,
                $result['message']
            );
            
            return $result;
        } catch (Exception $e) {
            // Log the error
            $module = Module::getInstanceByName('stockpricesync');
            $module->logSync(
                $shop['id_shop_remote'],
                'price',
                $product_reference,
                $combination_reference,
                null,
                null,
                null,
                $price,
                0,
                'Exception: ' . $e->getMessage()
            );
            
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process the sync queue
     */
    public function processQueue($limit = 50)
    {
        // Get pending items
        $sql = '
            SELECT * FROM `'._DB_PREFIX_.'stockpricesync_queue`
            WHERE status = "pending"
            ORDER BY priority DESC, date_add ASC
            LIMIT '.(int)$limit;
        
        $items = Db::getInstance()->executeS($sql) ?: [];
        
        if (empty($items)) {
            return [
                'success' => true,
                'message' => 'No items in queue',
                'processed' => 0,
                'errors' => 0
            ];
        }
        
        $processed = 0;
        $errors = 0;
        
        foreach ($items as $item) {
            // Mark as processing
            Db::getInstance()->update(
                'stockpricesync_queue',
                [
                    'status' => 'processing',
                    'attempts' => (int)$item['attempts'] + 1,
                    'date_upd' => date('Y-m-d H:i:s')
                ],
                'id_queue = ' . (int)$item['id_queue']
            );
            
            // Process based on sync type
            $success = true;
            $error_message = '';
            
            try {
                if ($item['sync_type'] == 'stock' || $item['sync_type'] == 'both') {
                    // Get shops that need stock sync
                    $shops = SPSRemoteShop::getActiveShopsWithStockSync();
                    
                    foreach ($shops as $shop) {
                        try {
                            $result = $this->sendStockToShop(
                                $shop,
                                $item['product_reference'],
                                $item['combination_reference'],
                                $item['quantity']
                            );
                            
                            if (!$result['success']) {
                                $success = false;
                                $error_message .= 'Shop '.$shop['name'].': '.$result['message'].'; ';
                            }
                        } catch (Exception $e) {
                            $success = false;
                            $error_message .= 'Exception in shop '.$shop['name'].': '.$e->getMessage().'; ';
                        }
                    }
                }
                
                if ($item['sync_type'] == 'price' || $item['sync_type'] == 'both') {
                    // Get shops that need price sync
                    $shops = SPSRemoteShop::getActiveShopsWithPriceSync();

                    foreach ($shops as $shop) {
                        try {
                            $base_price = (float)$item['price'];
                            $price_impact = isset($item['price_impact']) ? (float)$item['price_impact'] : 0;

                            // Apply percentage ONLY to base price
                            $adjusted_base = $base_price;
                            if (isset($shop['price_percentage']) && $shop['price_percentage'] != 0) {
                                $adjusted_base = $base_price * (1 + ($shop['price_percentage'] / 100));
                            }

                            // Final price = adjusted base + original impact (unchanged)
                            $final_price = $adjusted_base + $price_impact;

                            $result = $this->sendPriceToShop(
                                $shop,
                                $item['product_reference'],
                                $item['combination_reference'],
                                $final_price
                            );

                            if (!$result['success']) {
                                $success = false;
                                $error_message .= 'Shop '.$shop['name'].': '.$result['message'].'; ';
                            }
                        } catch (Exception $e) {
                            $success = false;
                            $error_message .= 'Exception in shop '.$shop['name'].': '.$e->getMessage().'; ';
                        }
                    }
                }
                
                // Update status
                if ($success) {
                    Db::getInstance()->update(
                        'stockpricesync_queue',
                        [
                            'status' => 'completed',
                            'date_upd' => date('Y-m-d H:i:s')
                        ],
                        'id_queue = ' . (int)$item['id_queue']
                    );
                    $processed++;
                } else {
                    // If too many attempts, mark as error
                    if ($item['attempts'] >= 3) {
                        Db::getInstance()->update(
                            'stockpricesync_queue',
                            [
                                'status' => 'error',
                                'message' => pSQL($error_message),
                                'date_upd' => date('Y-m-d H:i:s')
                            ],
                            'id_queue = ' . (int)$item['id_queue']
                        );
                        $errors++;
                    } else {
                        // Otherwise, retry later
                        Db::getInstance()->update(
                            'stockpricesync_queue',
                            [
                                'status' => 'pending',
                                'message' => pSQL($error_message),
                                'date_upd' => date('Y-m-d H:i:s')
                            ],
                            'id_queue = ' . (int)$item['id_queue']
                        );
                    }
                }
            } catch (Exception $e) {
                // Handle any unexpected exceptions
                Db::getInstance()->update(
                    'stockpricesync_queue',
                    [
                        'status' => $item['attempts'] >= 3 ? 'error' : 'pending',
                        'message' => 'Exception: ' . pSQL($e->getMessage()),
                        'date_upd' => date('Y-m-d H:i:s')
                    ],
                    'id_queue = ' . (int)$item['id_queue']
                );
                $errors++;
            }
        }
        
        // Add this to cleanup completed tasks
        $this->cleanupCompletedTasks();
        
        return [
            'success' => true,
            'message' => sprintf('Processed %d items, %d errors', $processed, $errors),
            'processed' => $processed,
            'errors' => $errors
        ];
    }
    
    /**
     * Clean up completed tasks older than 7 days
     */
    private function cleanupCompletedTasks()
    {
        $date = date('Y-m-d H:i:s', strtotime('-7 days'));
        
        Db::getInstance()->execute('
            DELETE FROM `'._DB_PREFIX_.'stockpricesync_queue`
            WHERE status = "completed" AND date_upd < "'.pSQL($date).'"
        ');
    }

    /**
     * Sync all products for a specific shop
     */
    public function syncShop($shop, $sync_type = 'both')
    {
        // Extend execution time to prevent timeout
        set_time_limit(300); // 5 minutes
        ignore_user_abort(true);

        // Get all products with their references
        $products = $this->getProductsWithReferences();
        
        if (empty($products)) {
            return [
                'success' => false,
                'message' => 'No products with references found'
            ];
        }
        
        $batch_size = (int)Configuration::get('STOCKPRICESYNC_BATCH_SIZE');
        $total_products = count($products);

        // Use queue mode for manual sync if more than 10 products to prevent timeout
        // This ensures the request returns quickly while processing continues in background
        $manual_sync_threshold = 10;

        if ($total_products > $manual_sync_threshold) {
            // Add all to queue
            $queued = 0;
            
            foreach ($products as $product) {
                // For each product and combination
                if ($sync_type == 'stock' || $sync_type == 'both') {
                    $this->addToQueue(
                        $product['reference'],
                        $product['combination_reference'],
                        'stock',
                        $product['quantity'],
                        null,
                        null
                    );
                    $queued++;
                }

                if ($sync_type == 'price' || $sync_type == 'both') {
                    $this->addToQueue(
                        $product['reference'],
                        $product['combination_reference'],
                        'price',
                        null,
                        $product['price'],
                        isset($product['price_impact']) ? $product['price_impact'] : 0
                    );
                    $queued++;
                }
            }
            
            // Process only a small initial batch (30 items) to return quickly
            // Remaining items will be processed via cron or manual queue processing
            $initial_batch = 30;
            $process_result = $this->processQueue($initial_batch);

            $remaining = $queued - $process_result['processed'];

            return [
                'success' => true,
                'message' => sprintf(
                    '%d items added to queue for %s. Processed: %d, Remaining in queue: %d. Use "Process Queue" to continue.',
                    $queued,
                    $shop->name,
                    $process_result['processed'],
                    $remaining
                ),
                'queued' => $queued,
                'processed' => $process_result['processed'],
                'remaining' => $remaining,
                'errors' => $process_result['errors']
            ];
        } else {
            // Process directly
            $processed = 0;
            $errors = 0;
            
            foreach ($products as $product) {
                if ($sync_type == 'stock' || $sync_type == 'both') {
                    $result = $this->sendStockToShop(
                        [
                            'id_shop_remote' => $shop->id_shop_remote,
                            'name' => $shop->name,
                            'url' => $shop->url,
                            'api_key' => $shop->api_key
                        ],
                        $product['reference'],
                        $product['combination_reference'],
                        $product['quantity']
                    );
                    
                    if ($result['success']) {
                        $processed++;
                    } else {
                        $errors++;
                    }
                }
                
                if ($sync_type == 'price' || $sync_type == 'both') {
                    $base_price = (float)$product['price'];
                    $price_impact = isset($product['price_impact']) ? (float)$product['price_impact'] : 0;

                    // Apply percentage ONLY to base price
                    $adjusted_base = $base_price;
                    if ($shop->price_percentage != 0) {
                        $adjusted_base = $base_price * (1 + ($shop->price_percentage / 100));
                    }

                    // Final price = adjusted base + original impact (unchanged)
                    $final_price = $adjusted_base + $price_impact;

                    $result = $this->sendPriceToShop(
                        [
                            'id_shop_remote' => $shop->id_shop_remote,
                            'name' => $shop->name,
                            'url' => $shop->url,
                            'api_key' => $shop->api_key,
                            'price_percentage' => $shop->price_percentage
                        ],
                        $product['reference'],
                        $product['combination_reference'],
                        $final_price
                    );

                    if ($result['success']) {
                        $processed++;
                    } else {
                        $errors++;
                    }
                }
            }
            
            return [
                'success' => true,
                'message' => sprintf('Processed %d products, %d errors for shop %s', $processed, $errors, $shop->name),
                'processed' => $processed,
                'errors' => $errors
            ];
        }
    }

    /**
     * Sync all products for all shops
     */
    public function syncAllShops($sync_type = 'both')
    {
        // Extend execution time to prevent timeout
        set_time_limit(300); // 5 minutes
        ignore_user_abort(true);

        // Get all active shops
        $shops = SPSRemoteShop::getActiveShops();
        
        if (empty($shops)) {
            return [
                'success' => false,
                'message' => 'No active shops found'
            ];
        }
        
        $results = [];
        $total_processed = 0;
        $total_errors = 0;
        $total_queued = 0;
        
        foreach ($shops as $shop_data) {
            $shop = new SPSRemoteShop($shop_data['id_shop_remote']);
            
            if (!Validate::isLoadedObject($shop)) {
                continue;
            }
            
            // Skip shops based on sync type
            if (($sync_type == 'stock' && !$shop->sync_stock) || 
                ($sync_type == 'price' && !$shop->sync_price)) {
                continue;
            }
            
            $result = $this->syncShop($shop, $sync_type);
            $results[$shop->id_shop_remote] = $result;
            
            if (isset($result['processed'])) {
                $total_processed += $result['processed'];
            }
            
            if (isset($result['errors'])) {
                $total_errors += $result['errors'];
            }
            
            if (isset($result['queued'])) {
                $total_queued += $result['queued'];
            }
        }
        
        // Ahora procesar un lote adicional de la cola si hay elementos pendientes
        if ($total_queued > 0) {
            $batch_size = (int)Configuration::get('STOCKPRICESYNC_BATCH_SIZE');
            $process_result = $this->processQueue($batch_size);
            $total_processed += $process_result['processed'];
            $total_errors += $process_result['errors'];
        }
        
        return [
            'success' => true,
            'message' => sprintf('Processed %d items, %d errors, %d queued across all shops', $total_processed, $total_errors, $total_queued),
            'processed' => $total_processed,
            'errors' => $total_errors,
            'queued' => $total_queued,
            'details' => $results
        ];
    }

    /**
     * Send data to remote shop
     */
    private function sendRequest($data, $url, $api_key)
    {
        // Check if verify SSL is enabled
        $verify_ssl = (bool)Configuration::get('STOCKPRICESYNC_VERIFY_SSL');
        
        // Convert data to JSON
        $json_data = json_encode($data);
        
        // Set up cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_data),
            'X-API-Key: ' . $api_key
        ]);
        
        // SSL verification settings
        if ($verify_ssl) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        }

        // Timeout settings to prevent hanging
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Max execution time: 30 seconds
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Max connection time: 10 seconds

        // Execute and get response
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        // Check for errors
        if ($error) {
            return [
                'success' => false,
                'message' => 'cURL error: ' . $error
            ];
        }
        
        if ($http_code != 200) {
            return [
                'success' => false,
                'message' => 'HTTP error ' . $http_code . ': ' . $response
            ];
        }
        
        // Parse JSON response
        $result = json_decode($response, true);
        
        if (!$result || !isset($result['success'])) {
            return [
                'success' => false,
                'message' => 'Invalid response from server'
            ];
        }
        
        return $result;
    }

    /**
     * Get all products with their references, stock and prices
     * OPTIMIZED: Single query with JOINs instead of N+1 queries
     */
    private function getProductsWithReferences()
    {
        $id_shop = (int)Context::getContext()->shop->id;

        // Get ALL products and combinations in a single optimized query with JOINs
        $sql = "
            SELECT
                p.id_product,
                p.reference AS product_reference,
                ps.price AS base_price,
                sa_product.quantity AS product_quantity,
                pa.id_product_attribute,
                pa.reference AS combination_reference,
                pa.price AS price_impact,
                sa_combo.quantity AS combination_quantity
            FROM "._DB_PREFIX_."product p
            LEFT JOIN "._DB_PREFIX_."product_shop ps
                ON (p.id_product = ps.id_product AND ps.id_shop = {$id_shop})
            LEFT JOIN "._DB_PREFIX_."stock_available sa_product
                ON (p.id_product = sa_product.id_product AND sa_product.id_product_attribute = 0 AND sa_product.id_shop = {$id_shop})
            LEFT JOIN "._DB_PREFIX_."product_attribute pa
                ON (p.id_product = pa.id_product AND pa.reference != '')
            LEFT JOIN "._DB_PREFIX_."stock_available sa_combo
                ON (pa.id_product = sa_combo.id_product AND pa.id_product_attribute = sa_combo.id_product_attribute AND sa_combo.id_shop = {$id_shop})
            WHERE p.reference != ''
            ORDER BY p.id_product, pa.id_product_attribute
        ";

        $rows = Db::getInstance()->executeS($sql);

        if (!$rows) {
            return [];
        }

        $result = [];
        $processed_products = [];

        foreach ($rows as $row) {
            $id_product = (int)$row['id_product'];
            $product_ref = $row['product_reference'];

            // Add base product only once (first time we see this product)
            if (!isset($processed_products[$id_product])) {
                $result[] = [
                    'id_product' => $id_product,
                    'reference' => $product_ref,
                    'combination_reference' => null,
                    'quantity' => (int)$row['product_quantity'],
                    'price' => (float)$row['base_price'],
                    'price_impact' => 0  // No impact for base product
                ];
                $processed_products[$id_product] = true;
            }

            // Add combination if exists
            if (!empty($row['id_product_attribute']) && !empty($row['combination_reference'])) {
                $base_price = (float)$row['base_price'];
                $price_impact = !empty($row['price_impact']) ? (float)$row['price_impact'] : 0;

                $result[] = [
                    'id_product' => $id_product,
                    'id_product_attribute' => (int)$row['id_product_attribute'],
                    'reference' => $product_ref,
                    'combination_reference' => $row['combination_reference'],
                    'quantity' => (int)$row['combination_quantity'],
                    'price' => $base_price,  // Store only base price
                    'price_impact' => $price_impact  // Store impact separately
                ];
            }
        }

        return $result;
    }
}