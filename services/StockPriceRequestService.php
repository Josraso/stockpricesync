<?php
/**
 * Stock Price Request Service
 * Handles requesting stock and price updates from main shop
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class StockPriceRequestService
{
    /**
     * URL of the main shop
     */
    private $main_shop_url;

    /**
     * API Key for authentication
     */
    private $api_key;

    /**
     * Name of this shop
     */
    private $shop_name;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->main_shop_url = rtrim(Configuration::get('STOCKPRICESYNC_MAIN_SHOP_URL'), '/');
        $this->api_key = Configuration::get('STOCKPRICESYNC_API_KEY');
        $this->shop_name = Configuration::get('STOCKPRICESYNC_SHOP_NAME');
    }

    /**
     * Request stock and price updates from the main shop
     */
    public function requestUpdates($sync_type = 'both')
    {
        try {
            // Check configuration
            if (empty($this->main_shop_url) || empty($this->api_key)) {
                throw new Exception('The connection configuration with the main shop is not valid.');
            }

            // URL of the request endpoint
            $url = $this->main_shop_url . '/modules/stockpricesync/api/request.php';
            
            // Get the URL of this shop for the main shop
            $shop_url = Context::getContext()->shop->getBaseURL(true);
            
            // Get local products with their references for filtering
            $local_products = $this->getLocalProductsWithReferences();
            
            // Prepare data for the request
            $data = [
                'shop_name' => $this->shop_name,
                'shop_url' => $shop_url,
                'sync_type' => $sync_type,
                'products' => $local_products,
                'batch_size' => 100 // Request in batches
            ];
            
            // Send request
            $result = $this->sendRequest($data, $url);
            
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            
            // Process response - handle batches if needed
            if (isset($result['batch_mode']) && $result['batch_mode']) {
                // Batch mode - process each batch
                $total_processed = 0;
                $batch_count = isset($result['batch_count']) ? (int)$result['batch_count'] : 0;
                
                for ($i = 0; $i < $batch_count; $i++) {
                    $batch_data = [
                        'shop_name' => $this->shop_name,
                        'shop_url' => $shop_url,
                        'sync_type' => $sync_type,
                        'batch_request' => true,
                        'batch_number' => $i
                    ];
                    
                    $batch_result = $this->sendRequest($batch_data, $url);
                    
                    if ($batch_result['success']) {
                        // Process this batch
                        $processed = $this->processUpdateBatch($batch_result['updates'], $sync_type);
                        $total_processed += $processed;
                    }
                }
                
                return [
                    'success' => true,
                    'message' => sprintf('Processed %d updates from main shop', $total_processed),
                    'processed' => $total_processed
                ];
            } else {
                // Direct mode - process updates
                $updates = isset($result['updates']) ? $result['updates'] : [];
                $processed = $this->processUpdateBatch($updates, $sync_type);
                
                return [
                    'success' => true,
                    'message' => sprintf('Processed %d updates from main shop', $processed),
                    'processed' => $processed
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Request update for a single product by reference
     */
    public function requestSingleProduct($product_reference, $sync_type = 'both')
    {
        try {
            // Check configuration
            if (empty($this->main_shop_url) || empty($this->api_key)) {
                throw new Exception('The connection configuration with the main shop is not valid.');
            }

            // URL of the request endpoint
            $url = $this->main_shop_url . '/modules/stockpricesync/api/request.php';

            // Get the URL of this shop for the main shop
            $shop_url = Context::getContext()->shop->getBaseURL(true);

            // Check if product exists locally
            $id_product = $this->findProductByReference($product_reference);
            if (!$id_product) {
                throw new Exception('Product not found with reference: ' . $product_reference);
            }

            // Get product and its combinations
            $product = new Product($id_product);
            $products_filter = [
                ['reference' => $product_reference]
            ];

            // Add combinations
            $combinations = $product->getAttributeCombinations();
            if ($combinations) {
                foreach ($combinations as $combo) {
                    if (!empty($combo['reference'])) {
                        $products_filter[] = [
                            'reference' => $product_reference,
                            'combination_reference' => $combo['reference']
                        ];
                    }
                }
            }

            // Prepare data for the request
            $data = [
                'shop_name' => $this->shop_name,
                'shop_url' => $shop_url,
                'sync_type' => $sync_type,
                'products' => $products_filter
            ];

            // Send request
            $result = $this->sendRequest($data, $url);

            if (!$result['success']) {
                throw new Exception($result['message']);
            }

            // Process updates
            $updates = isset($result['updates']) ? $result['updates'] : [];
            $processed = $this->processUpdateBatch($updates, $sync_type);

            return [
                'success' => true,
                'message' => sprintf(
                    'Product %s updated: %d items (%d combinations)',
                    $product_reference,
                    $processed,
                    count($combinations) ?: 0
                )
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Process a batch of updates
     */
    private function processUpdateBatch($updates, $sync_type)
    {
        if (empty($updates)) {
            return 0;
        }
        
        $processed = 0;
        $module = Module::getInstanceByName('stockpricesync');
        
        foreach ($updates as $update) {
            $product_reference = isset($update['product_reference']) ? $update['product_reference'] : '';
            $combination_reference = isset($update['combination_reference']) ? $update['combination_reference'] : null;
            
            if (empty($product_reference)) {
                continue;
            }
            
            // Find the product by reference
            $id_product = $this->findProductByReference($product_reference);
            if (!$id_product) {
                // Log that product wasn't found
                $module->logSync(
                    null,
                    $sync_type,
                    $product_reference,
                    $combination_reference,
                    null,
                    null,
                    null,
                    null,
                    0,
                    'Product not found by reference'
                );
                continue;
            }
            
            // Find the combination if needed
            $id_product_attribute = 0;
            if ($combination_reference) {
                $id_product_attribute = $this->findCombinationByReference($id_product, $combination_reference);
                if (!$id_product_attribute) {
                    // Log that combination wasn't found
                    $module->logSync(
                        null,
                        $sync_type,
                        $product_reference,
                        $combination_reference,
                        null,
                        null,
                        null,
                        null,
                        0,
                        'Combination not found by reference'
                    );
                    continue;
                }
            }
            
            // Update stock if needed
            if (($sync_type == 'stock' || $sync_type == 'both') && isset($update['quantity'])) {
                $old_quantity = StockAvailable::getQuantityAvailableByProduct($id_product, $id_product_attribute);
                $new_quantity = (int)$update['quantity'];
                
                // Update stock
                StockAvailable::setQuantity($id_product, $id_product_attribute, $new_quantity);
                
                // Log the stock update
                $module->logSync(
                    null,
                    'stock',
                    $product_reference,
                    $combination_reference,
                    $old_quantity,
                    $new_quantity,
                    null,
                    null,
                    1,
                    'Stock updated from main shop'
                );
                
                $processed++;
            }
            
            // Update price if needed
            if (($sync_type == 'price' || $sync_type == 'both') && isset($update['price'])) {
                $product = new Product($id_product);
                
                if (!Validate::isLoadedObject($product)) {
                    continue;
                }
                
                $old_price = $product->getPrice(true, $id_product_attribute);
                $new_price = (float)$update['price'];
                
                if ($id_product_attribute > 0) {
                    // Update combination price
                    $combination = new Combination($id_product_attribute);
                    if (Validate::isLoadedObject($combination)) {
                        // Calculate price impact
                        $base_price = $product->getPrice(true, 0);
                        $impact = $new_price - $base_price;
                        
                        $combination->price = $impact;
                        $combination->update();
                    }
                } else {
                    // Update product base price
                    $product->price = $new_price;
                    $product->update();
                }
                
                // Log the price update
                $module->logSync(
                    null,
                    'price',
                    $product_reference,
                    $combination_reference,
                    null,
                    null,
                    $old_price,
                    $new_price,
                    1,
                    'Price updated from main shop'
                );
                
                $processed++;
            }
        }
        
        return $processed;
    }
    
    /**
     * Find a product by reference
     */
    private function findProductByReference($reference)
    {
        return Db::getInstance()->getValue('
            SELECT id_product 
            FROM '._DB_PREFIX_.'product 
            WHERE reference = "'.pSQL($reference).'"
        ');
    }
    
    /**
     * Find a combination by reference
     */
    private function findCombinationByReference($id_product, $reference)
    {
        return Db::getInstance()->getValue('
            SELECT id_product_attribute 
            FROM '._DB_PREFIX_.'product_attribute 
            WHERE id_product = '.(int)$id_product.' AND reference = "'.pSQL($reference).'"
        ');
    }
    
    /**
     * Get local products with their references
     */
    private function getLocalProductsWithReferences()
    {
        $result = [];
        
        // Get products with references
        $products = Db::getInstance()->executeS("
            SELECT p.id_product, p.reference
            FROM "._DB_PREFIX_."product p
            WHERE p.reference != ''
            ORDER BY p.id_product
        ");
        
        if (!$products) {
            return $result;
        }
        
        foreach ($products as $product) {
            $id_product = (int)$product['id_product'];
            
            // Add main product
            $result[] = [
                'reference' => $product['reference'],
                'combination_reference' => null
            ];
            
            // Get combinations if any
            $combinations = Db::getInstance()->executeS("
                SELECT pa.id_product_attribute, pa.reference
                FROM "._DB_PREFIX_."product_attribute pa
                WHERE pa.id_product = ".$id_product." AND pa.reference != ''
            ");
            
            if ($combinations) {
                foreach ($combinations as $combination) {
                    // Add combination
                    $result[] = [
                        'reference' => $product['reference'],
                        'combination_reference' => $combination['reference']
                    ];
                }
            }
        }
        
        return $result;
    }

    /**
     * Send request to the main shop
     */
    private function sendRequest($data, $url)
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
            'X-API-Key: ' . $this->api_key
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
}