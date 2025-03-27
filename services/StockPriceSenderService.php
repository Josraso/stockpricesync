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
            return $this->addToQueue($product_reference, $combination_reference, 'stock', $quantity);
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
        if ($id_product_attribute > 0) {
            $combination = new Combination($id_product_attribute);
            if (Validate::isLoadedObject($combination) && !empty($combination->reference)) {
                $combination_reference = $combination->reference;
            }
        }
        
        // Get product price
        $price = $product->price; // El campo 'price' ya contiene el precio sin IVA
        
        // Check if we're in batch mode or real-time
        $batch_size = (int)Configuration::get('STOCKPRICESYNC_BATCH_SIZE');
        $use_batch = count($shops) > $batch_size;
        
        if ($use_batch) {
            // Add to queue for batch processing
            return $this->addToQueue($product_reference, $combination_reference, 'price', null, $price);
        } else {
            // Send directly to each shop
            $results = [];
            
            foreach ($shops as $shop) {
                // Apply percentage if configured
                $adjusted_price = $price;
                if (isset($shop['price_percentage']) && $shop['price_percentage'] != 0) {
                    $adjusted_price = $price * (1 + ($shop['price_percentage'] / 100));
                }
                
                $result = $this->sendPriceToShop(
                    $shop,
                    $product_reference,
                    $combination_reference,
                    $adjusted_price
                );
                
                $results[$shop['id_shop_remote']] = $result;
            }
            
            return ['success' => true, 'results' => $results];
        }
    }

    /**
     * Add an update to the queue
     */
    public function addToQueue($product_reference, $combination_reference, $sync_type, $quantity = null, $price = null)
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
                'processed' => 0
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
            
            if ($item['sync_type'] == 'stock' || $item['sync_type'] == 'both') {
                // Get shops that need stock sync
                $shops = SPSRemoteShop::getActiveShopsWithStockSync();
                
                foreach ($shops as $shop) {
                    $result = $this->sendStockToShop(
                        $shop,
                        $item['product_reference'],
                        $item['combination_reference'],
                        $item['quantity']
                    );
                    
                    if (!$result['success']) {
                        $success = false;
                    }
                }
            }
            
            if ($item['sync_type'] == 'price' || $item['sync_type'] == 'both') {
                // Get shops that need price sync
                $shops = SPSRemoteShop::getActiveShopsWithPriceSync();
                
                foreach ($shops as $shop) {
                    // Apply percentage if configured
                    $adjusted_price = $item['price'];
                    if (isset($shop['price_percentage']) && $shop['price_percentage'] != 0) {
                        $adjusted_price = $item['price'] * (1 + ($shop['price_percentage'] / 100));
                    }
                    
                    $result = $this->sendPriceToShop(
                        $shop,
                        $item['product_reference'],
                        $item['combination_reference'],
                        $adjusted_price
                    );
                    
                    if (!$result['success']) {
                        $success = false;
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
                            'date_upd' => date('Y-m-d H:i:s')
                        ],
                        'id_queue = ' . (int)$item['id_queue']
                    );
                }
            }
        }
        
        return [
            'success' => true,
            'message' => sprintf('Processed %d items, %d errors', $processed, $errors),
            'processed' => $processed,
            'errors' => $errors
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
     * Sync all products for a specific shop
     */
    public function syncShop($shop, $sync_type = 'both')
    {
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
        
        // If too many products, use batch mode
        if ($total_products > $batch_size) {
            // Add all to queue
            $queued = 0;
            
            foreach ($products as $product) {
                // For each product and combination
                if ($sync_type == 'stock' || $sync_type == 'both') {
                    $this->addToQueue(
                        $product['reference'],
                        $product['combination_reference'],
                        'stock',
                        $product['quantity']
                    );
                    $queued++;
                }
                
                if ($sync_type == 'price' || $sync_type == 'both') {
                    $this->addToQueue(
                        $product['reference'],
                        $product['combination_reference'],
                        'price',
                        null,
                        $product['price']
                    );
                    $queued++;
                }
            }
            
            return [
                'success' => true,
                'message' => sprintf('Added %d products to queue for shop %s', $queued, $shop->name),
                'queued' => $queued
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
                    // Apply percentage if configured
                    $adjusted_price = $product['price'];
                    if ($shop->price_percentage != 0) {
                        $adjusted_price = $product['price'] * (1 + ($shop->price_percentage / 100));
                    }
                    
                    $result = $this->sendPriceToShop(
                        [
                            'id_shop_remote' => $shop->id_shop_remote,
                            'url' => $shop->url,
                            'api_key' => $shop->api_key
                        ],
                        $product['reference'],
                        $product['combination_reference'],
                        $adjusted_price
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
        }
        
        return [
            'success' => true,
            'message' => sprintf('Processed %d items, %d errors across all shops', $total_processed, $total_errors),
            'processed' => $total_processed,
            'errors' => $total_errors,
            'details' => $results
        ];
    }

    /**
     * Get all products with their references, stock and prices
     */
    private function getProductsWithReferences()
    {
        $result = [];
        
        // Get products with references
        $products = Db::getInstance()->executeS("
            SELECT p.id_product, p.reference, ps.price
            FROM "._DB_PREFIX_."product p
            LEFT JOIN "._DB_PREFIX_."product_shop ps ON (p.id_product = ps.id_product AND ps.id_shop = ".(int)Context::getContext()->shop->id.")
            WHERE p.reference != ''
            ORDER BY p.id_product
        ");
        
        if (!$products) {
            return $result;
        }
        
        foreach ($products as $product) {
            $id_product = (int)$product['id_product'];
            
            // Get stock for the main product
            $quantity = StockAvailable::getQuantityAvailableByProduct($id_product, 0);
            
            // Add main product
            $result[] = [
                'id_product' => $id_product,
                'reference' => $product['reference'],
                'combination_reference' => null,
                'quantity' => (int)$quantity,
                'price' => (float)$product['price']
            ];
            
            // Get combinations if any
            $combinations = Db::getInstance()->executeS("
                SELECT pa.id_product_attribute, pa.reference
                FROM "._DB_PREFIX_."product_attribute pa
                WHERE pa.id_product = ".$id_product." AND pa.reference != ''
            ");
            
            if ($combinations) {
                foreach ($combinations as $combination) {
                    $id_product_attribute = (int)$combination['id_product_attribute'];
                    
                    // Get stock for this combination
                    $quantity = StockAvailable::getQuantityAvailableByProduct($id_product, $id_product_attribute);
                    
                    // Get price impact
                    $combination_obj = new Combination($id_product_attribute);
                    $impact = $combination_obj->price;
                    
                    // Calculate final price
                    $price = (float)$product['price'];
                    if ($impact != 0) {
                        $price += $impact;
                    }
                    
                    // Add combination
                    $result[] = [
                        'id_product' => $id_product,
                        'id_product_attribute' => $id_product_attribute,
                        'reference' => $product['reference'],
                        'combination_reference' => $combination['reference'],
                        'quantity' => (int)$quantity,
                        'price' => $price
                    ];
                }
            }
        }
        
        return $result;
    }
}