<?php
/**
 * API endpoint for testing connection
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

// Verify the shop is configured
$shop_type = Configuration::get('STOCKPRICESYNC_SHOP_TYPE');
if (empty($shop_type)) {
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'message' => 'This shop is not configured for StockPriceSync'
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

    // Get request data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Handle based on shop type
    if ($shop_type == 'MAIN') {
        // MAIN shop - verify API key against registered remote shops
        $shop = SPSRemoteShop::getByApiKey($api_key);

        if ($shop) {
            // Shop exists and is active

            // Update URL if provided
            if ($data && !empty($data['shop_url']) && empty($shop->url)) {
                $shop->url = $data['shop_url'];
                $shop->update();
            }

            // Log successful connection test
            $module = Module::getInstanceByName('stockpricesync');
            $module->logSync(
                $shop->id_shop_remote,
                'both',
                null,
                null,
                null,
                null,
                null,
                null,
                1,
                'Connection test successful from ' . $shop->name
            );

            // Return success
            echo json_encode([
                'success' => true,
                'message' => 'Connection established successfully with shop ' . $shop->name,
                'shop_id' => $shop->id_shop_remote
            ]);
        } else {
            // Check if it's an inactive shop
            if (SPSRemoteShop::isApiKeyForInactiveShop($api_key)) {
                throw new Exception('This shop is disabled in the main shop. Contact the main shop administrator to activate it.');
            }

            // Not found or invalid API key
            throw new Exception('Shop not authorized. The provided API key does not match any registered shop.');
        }
    } else {
        // CHILD shop - verify API key against stored API key
        $stored_api_key = Configuration::get('STOCKPRICESYNC_API_KEY');

        if ($api_key !== $stored_api_key) {
            throw new Exception('Invalid API key for this child shop');
        }

        // Return success
        $shop_name = Configuration::get('STOCKPRICESYNC_SHOP_NAME');
        echo json_encode([
            'success' => true,
            'message' => 'Connection established successfully with ' . $shop_name,
            'shop_name' => $shop_name,
            'shop_type' => 'CHILD'
        ]);
    }
} catch (Exception $e) {
    // Log error
    PrestaShopLogger::addLog(
        'StockPriceSync API Error: ' . $e->getMessage(),
        3, // Error level
        null,
        'SPSRemoteShop',
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