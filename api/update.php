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
    
    $id_product = Db::getInstance()->getValue('
        SELECT id_product 
        FROM '._DB_PREFIX_.'product 
        WHERE reference = "'.pSQL($product_reference).'"
    ');
    
    if (!$id_product) {
        throw new Exception('Product with reference ' . $product_reference . ' not found');
    }
    
    // Find combination if needed
    $id_product_attribute = 0;
    if ($combination_reference) {
        $id_product_attribute = Db::getInstance()->getValue('
            SELECT id_product_attribute 
            FROM '._DB_PREFIX_.'product_attribute 
            WHERE id_product = '.(int)$id_product.' AND reference = "'.pSQL($combination_reference).'"
        ');
        
        if (!$id_product_attribute) {
            throw new Exception('Combination with reference ' . $combination_reference . ' not found');
        }
    }
    
    // Load modules
    $module = Module::getInstanceByName('stockpricesync');
    
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
        $product = new Product($id_product);
        
        if (!Validate::isLoadedObject($product)) {
            throw new Exception('Could not load product with ID ' . $id_product);
        }
        
        $old_price = $product->getPrice(true, $id_product_attribute);
        $new_price = (float)$data['price'];
        
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
        
        // Log the update
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