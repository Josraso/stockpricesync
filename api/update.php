<?php
/**
 * API endpoint for updating stock and price (child shop)
 */

// Include PrestaShop
include_once(dirname(__FILE__) . '/../../../config/config.inc.php');
include_once(dirname(__FILE__) . '/../../../init.php');

// Setup headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Verify the shop is configured as child
if (Configuration::get('STOCKPRICESYNC_SHOP_TYPE') != 'CHILD') {
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'message' => 'This shop is not configured as child shop'
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
    
    // Verify API key matches configuration
    $configured_api_key = Configuration::get('STOCKPRICESYNC_API_KEY');
    if ($api_key !== $configured_api_key) {
        throw new Exception('Invalid API key');
    }
    
    // Get request data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data');
    }
    
    // Required fields
    if (!isset($data['product_reference']) || empty($data['product_reference'])) {
        throw new Exception('Product reference is required');
    }
    
    if (!isset($data['update_type']) || !in_array($data['update_type'], ['stock', 'price', 'both'])) {
        throw new Exception('Invalid update type');
    }
    
    // Find product by reference
    $product_reference = $data['product_reference'];
    $combination_reference = isset($data['combination_reference']) ? $data['combination_reference'] : null;

    // Log what we're looking for
    PrestaShopLogger::addLog(
        'StockPriceSync API: Looking for product_ref=['.$product_reference.'] combo_ref=['.($combination_reference ?: 'NULL').']',
        1,
        null,
        'API',
        0,
        true
    );

    try {
        $sql_product = 'SELECT id_product FROM '._DB_PREFIX_.'product WHERE reference = "'.pSQL($product_reference).'"';
        PrestaShopLogger::addLog('StockPriceSync API: Executing SQL: '.$sql_product, 1, null, 'API', 0, true);
        $id_product = Db::getInstance()->getValue($sql_product);
        PrestaShopLogger::addLog('StockPriceSync API: Found id_product='.$id_product, 1, null, 'API', 0, true);
    } catch (Exception $e) {
        PrestaShopLogger::addLog(
            'StockPriceSync API: SQL ERROR in product lookup - '.$e->getMessage().' SQL: '.$sql_product,
            3,
            null,
            'API',
            0,
            true
        );
        throw new Exception('Database error finding product: ' . $e->getMessage());
    }

    if (!$id_product) {
        throw new Exception('Product with reference ' . $product_reference . ' not found');
    }

    // Find combination if needed
    $id_product_attribute = 0;
    if ($combination_reference) {
        try {
            $sql_combo = 'SELECT id_product_attribute FROM '._DB_PREFIX_.'product_attribute WHERE id_product = '.(int)$id_product.' AND reference = "'.pSQL($combination_reference).'"';
            PrestaShopLogger::addLog('StockPriceSync API: Executing SQL: '.$sql_combo, 1, null, 'API', 0, true);
            $id_product_attribute = Db::getInstance()->getValue($sql_combo);
            PrestaShopLogger::addLog('StockPriceSync API: Found id_product_attribute='.$id_product_attribute, 1, null, 'API', 0, true);
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'StockPriceSync API: SQL ERROR in combination lookup - '.$e->getMessage().' SQL: '.$sql_combo,
                3,
                null,
                'API',
                0,
                true
            );
            throw new Exception('Database error finding combination: ' . $e->getMessage());
        }

        if (!$id_product_attribute) {
            throw new Exception('Combination with reference ' . $combination_reference . ' not found');
        }
    }

    // Load modules
    PrestaShopLogger::addLog('StockPriceSync API: Loading module...', 1, null, 'API', 0, true);
    $module = Module::getInstanceByName('stockpricesync');
    if (!$module) {
        throw new Exception('Could not load stockpricesync module');
    }
    PrestaShopLogger::addLog('StockPriceSync API: Module loaded successfully', 1, null, 'API', 0, true);
    
    // Update stock if needed
    if (($data['update_type'] == 'stock' || $data['update_type'] == 'both') && isset($data['quantity'])) {
        $old_quantity = StockAvailable::getQuantityAvailableByProduct($id_product, $id_product_attribute);
        $new_quantity = (int)$data['quantity'];
        
        // Update stock
        StockAvailable::setQuantity($id_product, $id_product_attribute, $new_quantity);
        
        // Log the update
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
    }
    
    // Update price if needed
    if (($data['update_type'] == 'price' || $data['update_type'] == 'both') && isset($data['price'])) {
        PrestaShopLogger::addLog('StockPriceSync API: Starting price update...', 1, null, 'API', 0, true);

        $product = new Product($id_product);

        if (!Validate::isLoadedObject($product)) {
            throw new Exception('Could not load product with ID ' . $id_product);
        }

        PrestaShopLogger::addLog('StockPriceSync API: Product loaded, getting old price...', 1, null, 'API', 0, true);

        try {
            $old_price = $product->getPrice(true, $id_product_attribute);
        } catch (Exception $e) {
            PrestaShopLogger::addLog('StockPriceSync API: ERROR getting old price - '.$e->getMessage(), 3, null, 'API', 0, true);
            throw new Exception('Error getting old price: ' . $e->getMessage());
        }

        $new_base_price = (float)$data['price'];
        $new_price_impact = isset($data['price_impact']) ? (float)$data['price_impact'] : 0;

        PrestaShopLogger::addLog('StockPriceSync API: Updating product price to '.$new_base_price, 1, null, 'API', 0, true);

        // ALWAYS update product base price first
        try {
            $product->price = $new_base_price;
            $product->update();
            PrestaShopLogger::addLog('StockPriceSync API: Product price updated successfully', 1, null, 'API', 0, true);
        } catch (Exception $e) {
            PrestaShopLogger::addLog('StockPriceSync API: ERROR updating product - '.$e->getMessage(), 3, null, 'API', 0, true);
            throw new Exception('Error updating product: ' . $e->getMessage());
        }

        if ($id_product_attribute > 0) {
            PrestaShopLogger::addLog('StockPriceSync API: Updating combination price impact to '.$new_price_impact, 1, null, 'API', 0, true);
            // Then update combination price impact
            $combination = new Combination($id_product_attribute);
            if (Validate::isLoadedObject($combination)) {
                try {
                    $combination->price = $new_price_impact;
                    $combination->update();
                    PrestaShopLogger::addLog('StockPriceSync API: Combination updated successfully', 1, null, 'API', 0, true);
                } catch (Exception $e) {
                    PrestaShopLogger::addLog('StockPriceSync API: ERROR updating combination - '.$e->getMessage(), 3, null, 'API', 0, true);
                    throw new Exception('Error updating combination: ' . $e->getMessage());
                }
            }
        }

        $new_price = $new_base_price + $new_price_impact;

        PrestaShopLogger::addLog('StockPriceSync API: Logging sync...', 1, null, 'API', 0, true);

        // Log the update
        try {
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
            PrestaShopLogger::addLog('StockPriceSync API: Sync logged successfully', 1, null, 'API', 0, true);
        } catch (Exception $e) {
            PrestaShopLogger::addLog('StockPriceSync API: ERROR logging sync - '.$e->getMessage(), 3, null, 'API', 0, true);
            // Don't throw here, log error but continue
        }
    }
    
    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'Update processed successfully'
    ]);
    
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