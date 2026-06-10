<?php
/**
 * API endpoint for requesting stock and price updates (main shop)
 */

// Include PrestaShop
include_once(dirname(__FILE__) . '/../../../config/config.inc.php');
include_once(dirname(__FILE__) . '/../../../init.php');

// Include module classes
include_once(dirname(__FILE__) . '/../classes/SPSRemoteShop.php');

// Setup headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Verify the shop is configured as main
if (Configuration::get('STOCKPRICESYNC_SHOP_TYPE') != 'MAIN') {
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'message' => 'This shop is not configured as main shop'
    ]));
}

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]));
}

try {
    // Get API key from header
    $api_key = '';
    if (isset($_SERVER['HTTP_X_API_KEY'])) {
        $api_key = $_SERVER['HTTP_X_API_KEY'];
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            $api_key = $matches[1];
        }
    }
    
    // Verify API key
    if (empty($api_key)) {
        throw new Exception('API key not provided');
    }
    
    // Look up shop by API key
    $shop = SPSRemoteShop::getByApiKey($api_key);
    
    if (!$shop) {
        // Check if the API key corresponds to an inactive shop
        if (SPSRemoteShop::isApiKeyForInactiveShop($api_key)) {
            throw new Exception('This shop is disabled in the main shop. Contact the main shop administrator to activate it.');
        }
        
        throw new Exception('Shop not authorized. The provided API key does not match any registered shop.');
    }
    
    // Get request data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data');
    }
    
    // Update shop URL if empty
    if (empty($shop->url) && !empty($data['shop_url'])) {
        $shop->url = $data['shop_url'];
        $shop->update();
    }
    
    // Get sync type
    $sync_type = isset($data['sync_type']) ? $data['sync_type'] : 'both';
    if (!in_array($sync_type, ['stock', 'price', 'both'])) {
        $sync_type = 'both';
    }
    
    // Check if this is a batch request
    $is_batch_request = isset($data['batch_request']) && $data['batch_request'];
    
    if ($is_batch_request) {
        // Process batch request
        $batch_number = isset($data['batch_number']) ? (int)$data['batch_number'] : 0;
        $updates = getProductUpdatesForBatch($batch_number, $shop, $sync_type);
        
        echo json_encode([
            'success' => true,
            'message' => 'Batch data provided',
            'updates' => $updates
        ]);
    } else {
        // Process initial request
        
        // Get product filter from request
        $product_filter = isset($data['products']) ? $data['products'] : [];
        
        // Check batch size
        $batch_size = isset($data['batch_size']) ? (int)$data['batch_size'] : 100;
        
        // Get all product data based on filter and sync type
        $all_updates = getAllProductUpdates($shop, $sync_type, $product_filter);
        
        // Decide whether to use batch mode
        $total_updates = count($all_updates);
        
        if ($total_updates > $batch_size) {
            // Store updates in temporary file for batching
            $batch_id = md5($shop->id_shop_remote . '_' . time() . '_' . uniqid());

            // Create temp directory if it doesn't exist
            $temp_dir = _PS_MODULE_DIR_ . 'stockpricesync/temp/';
            if (!is_dir($temp_dir)) {
                mkdir($temp_dir, 0755, true);
            }

            // Save batch data to file
            $batch_file = $temp_dir . 'batch_' . $batch_id . '.json';
            file_put_contents($batch_file, json_encode([
                'shop_id' => $shop->id_shop_remote,
                'created' => time(),
                'data' => $all_updates
            ]));

            // Clean old batch files (older than 1 hour)
            cleanOldBatchFiles($temp_dir);

            // Calculate number of batches
            $batch_count = ceil($total_updates / $batch_size);

            echo json_encode([
                'success' => true,
                'message' => 'Data will be provided in batches',
                'batch_mode' => true,
                'batch_id' => $batch_id,
                'batch_count' => $batch_count,
                'total_updates' => $total_updates
            ]);
        } else {
            // Send all updates at once
            echo json_encode([
                'success' => true,
                'message' => 'Data provided',
                'batch_mode' => false,
                'updates' => $all_updates,
                'total_updates' => $total_updates
            ]);
        }
    }
    
    // Log the request
    $module = Module::getInstanceByName('stockpricesync');
    $module->logSync(
        $shop->id_shop_remote,
        $sync_type,
        null,
        null,
        null,
        null,
        null,
        null,
        1,
        'Updates requested by shop ' . $shop->name
    );
    
} catch (Exception $e) {
    // Log error
    PrestaShopLogger::addLog(
        'StockPriceSync API Error: ' . $e->getMessage(),
        3, // Error level
        null,
        'Product',
        0,
        true
    );
    
    // Return error
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Clean old batch files (older than specified time)
 */
function cleanOldBatchFiles($temp_dir, $max_age = 3600)
{
    if (!is_dir($temp_dir)) {
        return;
    }

    $files = glob($temp_dir . 'batch_*.json');
    $now = time();

    foreach ($files as $file) {
        if (is_file($file) && ($now - filemtime($file)) > $max_age) {
            @unlink($file);
        }
    }
}

/**
 * Get updates for a specific batch
 */
function getProductUpdatesForBatch($batch_number, $shop, $sync_type)
{
    // Try to get batch_id from request
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $batch_id = isset($data['batch_id']) ? $data['batch_id'] : '';

    if (empty($batch_id)) {
        return [];
    }

    // Load batch data from file
    $temp_dir = _PS_MODULE_DIR_ . 'stockpricesync/temp/';
    $batch_file = $temp_dir . 'batch_' . $batch_id . '.json';

    if (!file_exists($batch_file)) {
        return [];
    }

    $batch_data = json_decode(file_get_contents($batch_file), true);

    if (!$batch_data || !isset($batch_data['data']) || !is_array($batch_data['data'])) {
        return [];
    }

    $all_updates = $batch_data['data'];
    $batch_size = 100;
    $start = $batch_number * $batch_size;

    return array_slice($all_updates, $start, $batch_size);
}

/**
 * Get all product updates based on filter and sync type
 */
function getAllProductUpdates($shop, $sync_type, $product_filter = [])
{
    $updates = [];
    
    // Build reference filter
    $reference_filter = [];
    
    if (!empty($product_filter)) {
        foreach ($product_filter as $product) {
            if (isset($product['reference']) && !empty($product['reference'])) {
                $key = $product['reference'];
                
                if (isset($product['combination_reference']) && !empty($product['combination_reference'])) {
                    $key .= '_' . $product['combination_reference'];
                }
                
                $reference_filter[$key] = $product;
            }
        }
    }
    
    // Get all products with their references
    $products = Db::getInstance()->executeS("
        SELECT p.id_product, p.reference, ps.price, s.quantity
        FROM " . _DB_PREFIX_ . "product p
        LEFT JOIN " . _DB_PREFIX_ . "product_shop ps ON (p.id_product = ps.id_product AND ps.id_shop = " . (int)Context::getContext()->shop->id . ")
        LEFT JOIN " . _DB_PREFIX_ . "stock_available s ON (p.id_product = s.id_product AND s.id_product_attribute = 0 AND s.id_shop = " . (int)Context::getContext()->shop->id . ")
        WHERE p.reference != ''
        ORDER BY p.id_product
    ");
    
    if (!$products) {
        return $updates;
    }
    
    // Get shop price percentage adjustment if applicable
    $price_percentage = $shop->price_percentage ? (float)$shop->price_percentage : 0;
    
    foreach ($products as $product) {
        $id_product = (int)$product['id_product'];
        $product_reference = $product['reference'];
        
        // Skip if product doesn't match the filter
        if (!empty($reference_filter) && !isset($reference_filter[$product_reference]) && !preg_grep('/^' . preg_quote($product_reference, '/') . '_/', array_keys($reference_filter))) {
            continue;
        }
        
        // Get product data
        $product_obj = new Product($id_product);
        
        // Base product update
        if (empty($reference_filter) || isset($reference_filter[$product_reference])) {
            $update = [
                'product_reference' => $product_reference,
                'combination_reference' => null
            ];
            
            // Add stock data if needed
            if ($sync_type == 'stock' || $sync_type == 'both') {
                if ($shop->sync_stock) {
                    $update['quantity'] = (int)$product['quantity'];
                }
            }
            
            // Add price data if needed
            if ($sync_type == 'price' || $sync_type == 'both') {
                if ($shop->sync_price) {
                    $base_price = $product_obj->getPrice();

                    // Apply percentage to base product (if configured)
                    if (!empty($price_percentage) && $price_percentage != 0) {
                        $base_price = $base_price * (1 + ($price_percentage / 100));
                    }

                    $update['price'] = (float)$base_price;
                    $update['price_impact'] = 0;  // Base product has no impact
                }
            }
            
            $updates[] = $update;
        }
        
        // Get combinations for this product
        $combinations = Db::getInstance()->executeS("
            SELECT pa.id_product_attribute, pa.reference, pa.price, s.quantity
            FROM " . _DB_PREFIX_ . "product_attribute pa
            LEFT JOIN " . _DB_PREFIX_ . "stock_available s ON (pa.id_product = s.id_product AND pa.id_product_attribute = s.id_product_attribute AND s.id_shop = " . (int)Context::getContext()->shop->id . ")
            WHERE pa.id_product = " . $id_product . " AND pa.reference != ''
            ORDER BY pa.id_product_attribute
        ");
        
        if ($combinations) {
            foreach ($combinations as $combination) {
                $combination_reference = $combination['reference'];
                $filter_key = $product_reference . '_' . $combination_reference;
                
                // Skip if combination doesn't match the filter
                if (!empty($reference_filter) && !isset($reference_filter[$filter_key])) {
                    continue;
                }
                
                $update = [
                    'product_reference' => $product_reference,
                    'combination_reference' => $combination_reference
                ];
                
                // Add stock data if needed
                if ($sync_type == 'stock' || $sync_type == 'both') {
                    if ($shop->sync_stock) {
                        $update['quantity'] = (int)$combination['quantity'];
                    }
                }
                
                // Add price data if needed
                if ($sync_type == 'price' || $sync_type == 'both') {
                    if ($shop->sync_price) {
                        // Get base product price (without tax, without combination impact)
                        $base_price = $product_obj->getPrice(false, null);

                        // Apply percentage to base price (if configured)
                        if (!empty($price_percentage) && $price_percentage != 0) {
                            $base_price = $base_price * (1 + ($price_percentage / 100));
                        }

                        // Get combination price impact (from pa.price column) - never changes
                        $price_impact = (float)$combination['price'];

                        // Send base and impact separately
                        $update['price'] = (float)$base_price;
                        $update['price_impact'] = (float)$price_impact;
                    }
                }
                
                $updates[] = $update;
            }
        }
    }
    
    return $updates;
}