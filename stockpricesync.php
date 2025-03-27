<?php
/**
 * Stock Price Sync Module
 *
 * @author      Developer
 * @copyright   2023 Your Company
 * @license     http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__).'/classes/SPSRemoteShop.php');
require_once(dirname(__FILE__).'/services/StockPriceSenderService.php');

class StockPriceSync extends Module
{
    public function __construct()
    {
        $this->name = 'stockpricesync';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Your Company';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => '8.99.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Stock & Price Synchronizer');
        $this->description = $this->l('Synchronizes stock and prices between independent PrestaShop stores.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');
    }

    /**
     * Install the module
     */
    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        // Create database tables
        if (!$this->createTables()) {
            return false;
        }

        // Register hooks
        if (!$this->registerHook('actionUpdateQuantity') ||
            !$this->registerHook('actionProductUpdate') ||
            !$this->registerHook('actionObjectStockAvailableUpdateAfter') ||
            !$this->registerHook('actionObjectProductUpdateAfter') ||
            !$this->registerHook('actionObjectSpecificPriceUpdateAfter') ||
            !$this->registerHook('actionProductPriceUpdateAfter') ||
            !$this->registerHook('displayBackOfficeHeader') ||
            !$this->registerHook('actionAdminControllerSetMedia')) {
            return false;
        }

        // Default configuration
        Configuration::updateValue('STOCKPRICESYNC_SHOP_TYPE', ''); // Empty = not configured
        Configuration::updateValue('STOCKPRICESYNC_MAIN_SHOP_URL', '');
        Configuration::updateValue('STOCKPRICESYNC_API_KEY', '');
        Configuration::updateValue('STOCKPRICESYNC_SHOP_NAME', Configuration::get('PS_SHOP_NAME'));
        Configuration::updateValue('STOCKPRICESYNC_SYNC_STOCK', 1); // Enable stock sync by default
        Configuration::updateValue('STOCKPRICESYNC_SYNC_PRICE', 1); // Enable price sync by default
        Configuration::updateValue('STOCKPRICESYNC_REAL_TIME_SYNC', 1); // Enable real time sync by default
        Configuration::updateValue('STOCKPRICESYNC_BATCH_SIZE', 50); // Default batch size
        Configuration::updateValue('STOCKPRICESYNC_VERIFY_SSL', 1); // Verify SSL by default

        return true;
    }

    /**
     * Uninstall the module
     */
    public function uninstall()
    {
        // Drop tables
        if (!$this->dropTables()) {
            return false;
        }

        // Delete configuration
        $configVars = [
            'STOCKPRICESYNC_SHOP_TYPE',
            'STOCKPRICESYNC_MAIN_SHOP_URL',
            'STOCKPRICESYNC_API_KEY',
            'STOCKPRICESYNC_SHOP_NAME',
            'STOCKPRICESYNC_SYNC_STOCK',
            'STOCKPRICESYNC_SYNC_PRICE',
            'STOCKPRICESYNC_REAL_TIME_SYNC',
            'STOCKPRICESYNC_BATCH_SIZE',
            'STOCKPRICESYNC_VERIFY_SSL'
        ];

        foreach ($configVars as $configVar) {
            Configuration::deleteByName($configVar);
        }

        return parent::uninstall();
    }

    /**
     * Create database tables
     */
    private function createTables()
    {
        $sql = [];

        // Remote shops table
        $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'stockpricesync_shop` (
            `id_shop_remote` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `url` VARCHAR(255) NOT NULL,
            `api_key` VARCHAR(255) NOT NULL,
            `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            `sync_stock` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            `sync_price` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1, 
            `price_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_shop_remote`)
        ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

        // Sync queue table
        $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'stockpricesync_queue` (
            `id_queue` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_shop_remote` INT(10) UNSIGNED DEFAULT NULL,
            `product_reference` VARCHAR(64) NOT NULL,
            `combination_reference` VARCHAR(64) DEFAULT NULL,
            `sync_type` ENUM("stock", "price", "both") NOT NULL DEFAULT "both",
            `quantity` INT(11) DEFAULT NULL,
            `price` DECIMAL(20,6) DEFAULT NULL,
            `specific_price` TEXT DEFAULT NULL,
            `priority` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            `status` ENUM("pending", "processing", "completed", "error") NOT NULL DEFAULT "pending",
            `attempts` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_queue`),
            INDEX `product_idx` (`product_reference`, `combination_reference`),
            INDEX `status_idx` (`status`)
        ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

        // Sync log table
$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'stockpricesync_log` (
    `id_log` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_shop_remote` INT(10) UNSIGNED DEFAULT NULL,
    `sync_type` ENUM("stock", "price", "both") NOT NULL DEFAULT "both",
    `product_reference` VARCHAR(64) DEFAULT NULL,
    `combination_reference` VARCHAR(64) DEFAULT NULL,
    `quantity_old` INT(11) DEFAULT NULL,
    `quantity_new` INT(11) DEFAULT NULL,
    `price_old` DECIMAL(20,6) DEFAULT NULL,
    `price_new` DECIMAL(20,6) DEFAULT NULL,
    `status` TINYINT(1) NOT NULL DEFAULT 0,
    `message` TEXT DEFAULT NULL,
    `log_level` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_log`),
    INDEX `shop_product_idx` (`id_shop_remote`, `product_reference`),
    INDEX `date_idx` (`date_add`),
    INDEX `status_idx` (`status`),
    INDEX `log_level_idx` (`log_level`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

        // Execute queries
        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Drop database tables
     */
    private function dropTables()
    {
        $sql = [
            'DROP TABLE IF EXISTS `'._DB_PREFIX_.'stockpricesync_shop`',
            'DROP TABLE IF EXISTS `'._DB_PREFIX_.'stockpricesync_queue`',
            'DROP TABLE IF EXISTS `'._DB_PREFIX_.'stockpricesync_log`'
        ];

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Module configuration page
     */
    public function getContent()
    {
        $output = '';
        $errors = array();
        $confirmations = array();

        // Get shop type and if it's configured
        $shop_type = Configuration::get('STOCKPRICESYNC_SHOP_TYPE');
        $shop_configured = !empty($shop_type);

        // Reset shop type (completely reset module)
        if (Tools::isSubmit('submit_shop_type') && Tools::getValue('STOCKPRICESYNC_SHOP_TYPE') === '') {
            // If already configured, reset everything
            if ($shop_configured) {
                $this->resetSyncData();
                Configuration::updateValue('STOCKPRICESYNC_SHOP_TYPE', '');
                Configuration::updateValue('STOCKPRICESYNC_MAIN_SHOP_URL', '');
                Configuration::updateValue('STOCKPRICESYNC_API_KEY', '');
                $confirmations[] = $this->l('All synchronization data has been deleted.');
                $shop_configured = false;
                $shop_type = '';
            }
        }
        // Set shop type
        elseif (Tools::isSubmit('submit_shop_type')) {
            $new_shop_type = Tools::getValue('STOCKPRICESYNC_SHOP_TYPE');
            if (!empty($new_shop_type)) {
                Configuration::updateValue('STOCKPRICESYNC_SHOP_TYPE', $new_shop_type);
                $confirmations[] = $this->l('Shop type has been configured successfully.');
                $shop_type = $new_shop_type;
                $shop_configured = true;
            } else {
                $errors[] = $this->l('You must select a shop type.');
            }
        }

        // Process main shop configuration
        if (Tools::isSubmit('submit_main_config') && $shop_type == 'MAIN') {
            $sync_stock = (int)Tools::getValue('STOCKPRICESYNC_SYNC_STOCK');
            $sync_price = (int)Tools::getValue('STOCKPRICESYNC_SYNC_PRICE');
            $real_time_sync = (int)Tools::getValue('STOCKPRICESYNC_REAL_TIME_SYNC');
            $batch_size = (int)Tools::getValue('STOCKPRICESYNC_BATCH_SIZE');
            
            Configuration::updateValue('STOCKPRICESYNC_SYNC_STOCK', $sync_stock);
            Configuration::updateValue('STOCKPRICESYNC_SYNC_PRICE', $sync_price);
            Configuration::updateValue('STOCKPRICESYNC_REAL_TIME_SYNC', $real_time_sync);
            Configuration::updateValue('STOCKPRICESYNC_BATCH_SIZE', $batch_size);
            
            $confirmations[] = $this->l('Main shop configuration has been updated successfully.');
        }

        // Process child shop configuration
        if (Tools::isSubmit('submit_child_config') && $shop_type == 'CHILD') {
            $main_shop_url = Tools::getValue('STOCKPRICESYNC_MAIN_SHOP_URL');
            $api_key = Tools::getValue('STOCKPRICESYNC_API_KEY');
            $shop_name = Tools::getValue('STOCKPRICESYNC_SHOP_NAME');
            $verify_ssl = (int)Tools::getValue('STOCKPRICESYNC_VERIFY_SSL');
            
            if (empty($main_shop_url) || !Validate::isUrl($main_shop_url)) {
                $errors[] = $this->l('Main shop URL is not valid.');
            } elseif (empty($api_key)) {
                $errors[] = $this->l('API Key is not valid.');
            } elseif (empty($shop_name)) {
                $errors[] = $this->l('Shop name is not valid.');
            } else {
                Configuration::updateValue('STOCKPRICESYNC_MAIN_SHOP_URL', $main_shop_url);
                Configuration::updateValue('STOCKPRICESYNC_API_KEY', $api_key);
                Configuration::updateValue('STOCKPRICESYNC_SHOP_NAME', $shop_name);
                Configuration::updateValue('STOCKPRICESYNC_VERIFY_SSL', $verify_ssl);
                $confirmations[] = $this->l('Child shop configuration has been updated successfully.');
            }
        }

        // Ajax handling for API key generation
        if (Tools::isSubmit('ajax') && Tools::getValue('action') == 'generateApiKey' && Tools::getValue('ajax') == 1) {
            $this->ajaxProcessGenerateApiKey();
            die();
        }

        // Process manual synchronization actions
        if ($shop_configured) {
            if ($shop_type == 'CHILD') {
                // Child shop - request stock & prices
                if (Tools::isSubmit('request_stock_prices')) {
                    // Load request service and request updates
                    require_once(dirname(__FILE__).'/services/StockPriceRequestService.php');
                    $requestService = new StockPriceRequestService();
                    $result = $requestService->requestUpdates();
                    
                    if ($result['success']) {
                        $confirmations[] = $this->l('Stock and prices requested successfully.');
                    } else {
                        $errors[] = $this->l('Error requesting stock and prices: ') . $result['message'];
                    }
                }
            } else {
                // Main shop - add or update remote shop
                if (Tools::isSubmit('submit_add_shop') || Tools::isSubmit('submit_edit_shop')) {
                    $id_shop_remote = Tools::isSubmit('submit_edit_shop') ? (int)Tools::getValue('id_shop_remote') : null;
                    $name = Tools::getValue('name');
                    $url = Tools::getValue('url');
                    $api_key = Tools::getValue('api_key', $this->generateApiKey());
                    $active = (int)Tools::getValue('active', 1);
                    $sync_stock = (int)Tools::getValue('sync_stock', 1);
                    $sync_price = (int)Tools::getValue('sync_price', 1);
                    $price_percentage = (float)Tools::getValue('price_percentage', 0);
                    
                    if (empty($name)) {
                        $errors[] = $this->l('Shop name is required.');
                    } elseif (empty($url) || !Validate::isUrl($url)) {
                        $errors[] = $this->l('Shop URL is not valid.');
                    } else {
                        $shop = new SPSRemoteShop($id_shop_remote);
                        
                        if (!Validate::isLoadedObject($shop) && $id_shop_remote) {
                            $errors[] = $this->l('Remote shop not found.');
                        } else {
                            $shop->name = $name;
                            $shop->url = $url;
                            $shop->api_key = $api_key;
                            $shop->active = $active;
                            $shop->sync_stock = $sync_stock;
                            $shop->sync_price = $sync_price;
                            $shop->price_percentage = $price_percentage;
                            
                            if ($id_shop_remote) {
                                $shop->date_upd = date('Y-m-d H:i:s');
                                if (!$shop->update()) {
                                    $errors[] = $this->l('Error updating remote shop.');
                                } else {
                                    $confirmations[] = $this->l('Remote shop updated successfully.');
                                }
                            } else {
                                $shop->date_add = date('Y-m-d H:i:s');
                                $shop->date_upd = date('Y-m-d H:i:s');
                                if (!$shop->add()) {
                                    $errors[] = $this->l('Error adding remote shop.');
                                } else {
                                    $confirmations[] = $this->l('Remote shop added successfully.');
                                }
                            }
                        }
                    }
                }
                
                // Delete remote shop
                if (Tools::isSubmit('delete_shop') && Tools::isSubmit('id_shop_remote')) {
                    $id_shop_remote = (int)Tools::getValue('id_shop_remote');
                    $shop = new SPSRemoteShop($id_shop_remote);
                    
                    if (!Validate::isLoadedObject($shop)) {
                        $errors[] = $this->l('Remote shop not found.');
                    } else {
                        if ($shop->delete()) {
                            $confirmations[] = $this->l('Remote shop deleted successfully.');
                        } else {
                            $errors[] = $this->l('Error deleting remote shop.');
                        }
                    }
                }
                
                // Generate new API key for a shop
                if (Tools::isSubmit('generate_api_key') && Tools::isSubmit('id_shop_remote')) {
                    $id_shop_remote = (int)Tools::getValue('id_shop_remote');
                    $shop = new SPSRemoteShop($id_shop_remote);
                    
                    if (!Validate::isLoadedObject($shop)) {
                        $errors[] = $this->l('Remote shop not found.');
                    } else {
                        $shop->api_key = $this->generateApiKey();
                        $shop->date_upd = date('Y-m-d H:i:s');
                        
                        if ($shop->update()) {
                            $confirmations[] = $this->l('API key generated successfully.');
                        } else {
                            $errors[] = $this->l('Error generating API key.');
                        }
                    }
                }
                
                // Manual sync for a specific shop
                if (Tools::isSubmit('sync_shop') && Tools::isSubmit('id_shop_remote')) {
                    $id_shop_remote = (int)Tools::getValue('id_shop_remote');
                    $sync_type = Tools::getValue('sync_type', 'both');
                    
                    $shop = new SPSRemoteShop($id_shop_remote);
                    
                    if (!Validate::isLoadedObject($shop)) {
                        $errors[] = $this->l('Remote shop not found.');
                    } else {
                        require_once(dirname(__FILE__).'/services/StockPriceSenderService.php');
                        $sender = new StockPriceSenderService();
                        $result = $sender->syncShop($shop, $sync_type);
                        
                        if ($result['success']) {
                            $confirmations[] = sprintf($this->l('Synchronization with shop %s completed: %s'), $shop->name, $result['message']);
                        } else {
                            $errors[] = sprintf($this->l('Error syncing with shop %s: %s'), $shop->name, $result['message']);
                        }
                    }
                }
                
                // Manual sync for all shops
                if (Tools::isSubmit('sync_all_shops')) {
                    $sync_type = Tools::getValue('sync_type', 'both');
                    
                    require_once(dirname(__FILE__).'/services/StockPriceSenderService.php');
                    $sender = new StockPriceSenderService();
                    $result = $sender->syncAllShops($sync_type);
                    
                    if ($result['success']) {
                        $confirmations[] = $this->l('Synchronization with all shops completed.') . ' ' . $result['message'];
                    } else {
                        $errors[] = $this->l('Error syncing with shops: ') . $result['message'];
                    }
                }
            }
        }

        // Display errors and confirmations
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $output .= $this->displayError($error);
            }
        }
        
        if (!empty($confirmations)) {
            foreach ($confirmations as $confirmation) {
                $output .= $this->displayConfirmation($confirmation);
            }
        }

// Prepare data for view
$view_data = [
    'module_dir' => $this->_path,
    'shop_type' => $shop_type,
    'shop_configured' => $shop_configured,
    'current_link' => $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name,
    'main_shop_url' => Configuration::get('STOCKPRICESYNC_MAIN_SHOP_URL'),
    'api_key' => Configuration::get('STOCKPRICESYNC_API_KEY'),
    'shop_name' => Configuration::get('STOCKPRICESYNC_SHOP_NAME'),
    'sync_stock' => (bool)Configuration::get('STOCKPRICESYNC_SYNC_STOCK'),
    'sync_price' => (bool)Configuration::get('STOCKPRICESYNC_SYNC_PRICE'),
    'real_time_sync' => (bool)Configuration::get('STOCKPRICESYNC_REAL_TIME_SYNC'),
    'batch_size' => (int)Configuration::get('STOCKPRICESYNC_BATCH_SIZE'),
    'verify_ssl' => (bool)Configuration::get('STOCKPRICESYNC_VERIFY_SSL'),
    'link' => $this->context->link,
];

// Assign smarty variables
$this->context->smarty->assign($view_data);

        // Add specific data based on shop type
if ($shop_configured) {
    if ($shop_type == 'MAIN') {
        // Get remote shops
        $shops = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'stockpricesync_shop` ORDER BY name ASC');
        $this->context->smarty->assign('shops', $shops);
                
                // View specific shop details
                if (Tools::isSubmit('view_shop') && Tools::isSubmit('id_shop_remote')) {
                    $id_shop_remote = (int)Tools::getValue('id_shop_remote');
                    $shop = new SPSRemoteShop($id_shop_remote);
                    
                    if (Validate::isLoadedObject($shop)) {
                        $view_data['view_shop'] = $shop;
                        
                        // Get sync stats for this shop
                        $stats = [
                            'stock_syncs' => $this->getShopSyncCount($id_shop_remote, 'stock'),
                            'price_syncs' => $this->getShopSyncCount($id_shop_remote, 'price'),
                            'success_rate' => $this->getShopSuccessRate($id_shop_remote),
                            'last_sync' => $this->getLastShopSync($id_shop_remote),
                        ];
                        
                        $view_data['shop_stats'] = $stats;
                    }
                }
                // Edit shop form
                elseif (Tools::isSubmit('edit_shop') && Tools::isSubmit('id_shop_remote')) {
                    $id_shop_remote = (int)Tools::getValue('id_shop_remote');
                    $shop = new SPSRemoteShop($id_shop_remote);
                    
                    if (Validate::isLoadedObject($shop)) {
                        $view_data['edit_shop'] = $shop;
                    }
                }
                // Shop list view
                elseif (Tools::isSubmit('view_shops')) {
                    $view_data['auto_api_key'] = $this->generateApiKey();
                }
                
               // Get recent sync logs - asegurarse de que se obtienen correctamente
$logs = Db::getInstance()->executeS('
    SELECT l.*, s.name as shop_name 
    FROM `'._DB_PREFIX_.'stockpricesync_log` l
    LEFT JOIN `'._DB_PREFIX_.'stockpricesync_shop` s ON (l.id_shop_remote = s.id_shop_remote)
    ORDER BY l.date_add DESC 
    LIMIT 10
');

// Asignar directamente a Smarty en lugar de a view_data
$this->context->smarty->assign('recent_logs', $logs);
                
                
                
                // Get sync queue status
                $pending_count = (int)Db::getInstance()->getValue('
                    SELECT COUNT(*) FROM `'._DB_PREFIX_.'stockpricesync_queue`
                    WHERE status = "pending"
                ');
                
                $view_data['pending_syncs'] = $pending_count;
            } 
            else { // CHILD shop
                // Get recent sync logs for this shop (child receives data)
                $logs = Db::getInstance()->executeS('
                    SELECT * FROM `'._DB_PREFIX_.'stockpricesync_log`
                    ORDER BY date_add DESC 
                    LIMIT 10
                ');
                
                $view_data['recent_logs'] = $logs;
            }
        }
        
        // Add JS and CSS
        $this->context->controller->addJS($this->_path . 'views/js/admin.js');
        $this->context->controller->addCSS($this->_path . 'views/css/admin.css');

        // Render template based on configuration
        if (!$shop_configured) {
            // Initial setup wizard
            $output .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/setup_wizard.tpl');
        } elseif ($shop_type == 'MAIN') {
            if (Tools::isSubmit('view_shop') || Tools::isSubmit('edit_shop')) {
                // Shop details/edit view
                $output .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/shop_details.tpl');
            }elseif (Tools::isSubmit('view_logs')) {
    // Logs view - asegurarse de que se obtienen y asignan los logs
    $page = (int)Tools::getValue('page', 1);
    $items_per_page = (int)Tools::getValue('items_per_page', 20);
    
    // Recuperar los logs - por ahora sin filtros
    $logs = Db::getInstance()->executeS('
        SELECT l.*, s.name as shop_name 
        FROM `'._DB_PREFIX_.'stockpricesync_log` l
        LEFT JOIN `'._DB_PREFIX_.'stockpricesync_shop` s ON (l.id_shop_remote = s.id_shop_remote)
        ORDER BY l.date_add DESC 
        LIMIT ' . (($page - 1) * $items_per_page) . ', ' . $items_per_page
    );
    
    // Contar el total para la paginación
    $total_logs = Db::getInstance()->getValue('
        SELECT COUNT(*) 
        FROM `'._DB_PREFIX_.'stockpricesync_log`
    ');
    
    // Asignar a Smarty
    $this->context->smarty->assign('logs', $logs);
    $this->context->smarty->assign('pagination_page', $page);
    $this->context->smarty->assign('pagination_items_per_page', $items_per_page);
    $this->context->smarty->assign('pagination_total_pages', ceil($total_logs / $items_per_page));
    
    // Stats para el panel superior
    $stats = [
        'total' => $total_logs,
        'success' => Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'stockpricesync_log` WHERE status = 1'),
        'error' => Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'stockpricesync_log` WHERE status = 0'),
        'stock_syncs' => Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'stockpricesync_log` WHERE sync_type = "stock"'),
        'price_syncs' => Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'stockpricesync_log` WHERE sync_type = "price"'),
    ];
    
    if ($stats['total'] > 0) {
        $stats['success_rate'] = round(($stats['success'] / $stats['total']) * 100);
    } else {
        $stats['success_rate'] = 0;
    }
    
    $this->context->smarty->assign('stats', $stats);
    
    $output .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/logs.tpl');

            } elseif (Tools::isSubmit('view_shops')) {
                // Shops list view
                $output .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/shop_list.tpl');
            } else {
                // Main shop dashboard
                $output .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/main_dashboard.tpl');
            }
        } else {
            if (Tools::isSubmit('view_logs')) {
                // Logs view
                $output .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/logs.tpl');
            } else {
                // Child shop dashboard
                $output .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/child_dashboard.tpl');
            }
        }

        return $output;
    }

    /**
     * AJAX handler for the module
     */
    public function ajaxProcessGenerateApiKey()
    {
        header('Content-Type: application/json');
        
        die(json_encode([
            'success' => true,
            'api_key' => $this->generateApiKey()
        ]));
    }

    /**
     * Reset all sync data when changing shop type
     */
    private function resetSyncData()
    {
        try {
            // Clear all tables
            Db::getInstance()->execute('TRUNCATE TABLE `'._DB_PREFIX_.'stockpricesync_shop`');
            Db::getInstance()->execute('TRUNCATE TABLE `'._DB_PREFIX_.'stockpricesync_queue`');
            Db::getInstance()->execute('TRUNCATE TABLE `'._DB_PREFIX_.'stockpricesync_log`');
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Generate a random API key
     */
    private function generateApiKey()
    {
        return md5(uniqid(mt_rand(), true));
    }
    
   /**
     * Get sync count for a shop by type
     */
    private function getShopSyncCount($id_shop_remote, $sync_type = null)
    {
        $sql = 'SELECT COUNT(*) FROM `'._DB_PREFIX_.'stockpricesync_log` 
                WHERE id_shop_remote = '.(int)$id_shop_remote;
        
        if ($sync_type) {
            $sql .= ' AND sync_type = "'.pSQL($sync_type).'"';
        }
        
        return (int)Db::getInstance()->getValue($sql);
    }
    
    /**
     * Get success rate for a shop
     */
    private function getShopSuccessRate($id_shop_remote)
    {
        $total = $this->getShopSyncCount($id_shop_remote);
        
        if ($total == 0) {
            return 0;
        }
        
        $success = (int)Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `'._DB_PREFIX_.'stockpricesync_log` 
            WHERE id_shop_remote = '.(int)$id_shop_remote.' AND status = 1
        ');
        
        return round(($success / $total) * 100);
    }
    
    /**
     * Get last sync for a shop
     */
    private function getLastShopSync($id_shop_remote)
    {
        return Db::getInstance()->getValue('
            SELECT date_add FROM `'._DB_PREFIX_.'stockpricesync_log` 
            WHERE id_shop_remote = '.(int)$id_shop_remote.'
            ORDER BY date_add DESC
            LIMIT 1
        ');
    }
    
 /**
 * Log sync action
 */
public function logSync($id_shop_remote, $sync_type, $product_reference, $combination_reference = null, 
                       $quantity_old = null, $quantity_new = null, $price_old = null, $price_new = null, 
                       $status = 1, $message = '', $log_level = 1)
{
    return Db::getInstance()->insert('stockpricesync_log', [
        'id_shop_remote' => $id_shop_remote ? (int)$id_shop_remote : null,
        'sync_type' => pSQL($sync_type),
        'product_reference' => $product_reference ? pSQL($product_reference) : null,
        'combination_reference' => $combination_reference ? pSQL($combination_reference) : null,
        'quantity_old' => $quantity_old !== null ? (int)$quantity_old : null,
        'quantity_new' => $quantity_new !== null ? (int)$quantity_new : null,
        'price_old' => $price_old !== null ? (float)$price_old : null,
        'price_new' => $price_new !== null ? (float)$price_new : null,
        'status' => (int)$status,
        'message' => pSQL($message),
        'log_level' => (int)$log_level,
        'date_add' => date('Y-m-d H:i:s')
    ]);
}
    
    /**
     * Hook for stock updates
     */
    public function hookActionUpdateQuantity($params)
    {
        // Only for main shop
        if (Configuration::get('STOCKPRICESYNC_SHOP_TYPE') != 'MAIN' || 
            !Configuration::get('STOCKPRICESYNC_SYNC_STOCK') || 
            !Configuration::get('STOCKPRICESYNC_REAL_TIME_SYNC')) {
            return;
        }
        
        // Get product information
        $id_product = isset($params['id_product']) ? (int)$params['id_product'] : 0;
        $id_product_attribute = isset($params['id_product_attribute']) ? (int)$params['id_product_attribute'] : 0;
        
        if (!$id_product) {
            return;
        }
        
        // Get product reference
        $product = new Product($id_product);
        if (!Validate::isLoadedObject($product) || empty($product->reference)) {
            return; // Skip products without reference
        }
        
        // Get combination reference if needed
        $combination_reference = null;
        if ($id_product_attribute > 0) {
            $combination = new Combination($id_product_attribute);
            if (Validate::isLoadedObject($combination) && !empty($combination->reference)) {
                $combination_reference = $combination->reference;
            }
        }
        
        // Get new quantity
        $quantity = StockAvailable::getQuantityAvailableByProduct($id_product, $id_product_attribute);
        
        // Process stock update
        require_once(dirname(__FILE__).'/services/StockPriceSenderService.php');
        $sender = new StockPriceSenderService();
        $sender->processStockUpdate($product->reference, $combination_reference, $quantity);
    }
    
    /**
     * Hook for price updates
     */
    public function hookActionProductPriceUpdateAfter($params)
    {
        // Only for main shop
        if (Configuration::get('STOCKPRICESYNC_SHOP_TYPE') != 'MAIN' || 
            !Configuration::get('STOCKPRICESYNC_SYNC_PRICE') || 
            !Configuration::get('STOCKPRICESYNC_REAL_TIME_SYNC')) {
            return;
        }
        
        // Get product information
        if (!isset($params['id_product']) || !(int)$params['id_product']) {
            return;
        }
        
        $id_product = (int)$params['id_product'];
        
        // Get product reference
        $product = new Product($id_product);
        if (!Validate::isLoadedObject($product) || empty($product->reference)) {
            return; // Skip products without reference
        }
        
        // Process price update for the product and all its combinations
        require_once(dirname(__FILE__).'/services/StockPriceSenderService.php');
        $sender = new StockPriceSenderService();
        $sender->processPriceUpdate($product);
    }
    
    /**
     * Hook for specific price updates
     */
    public function hookActionObjectSpecificPriceUpdateAfter($params)
    {
        // Only for main shop
        if (Configuration::get('STOCKPRICESYNC_SHOP_TYPE') != 'MAIN' || 
            !Configuration::get('STOCKPRICESYNC_SYNC_PRICE') || 
            !Configuration::get('STOCKPRICESYNC_REAL_TIME_SYNC')) {
            return;
        }
        
        // Get the specific price object
        if (!isset($params['object']) || !($params['object'] instanceof SpecificPrice)) {
            return;
        }
        
        $specificPrice = $params['object'];
        $id_product = (int)$specificPrice->id_product;
        
        // Get product reference
        $product = new Product($id_product);
        if (!Validate::isLoadedObject($product) || empty($product->reference)) {
            return; // Skip products without reference
        }
        
        // Process price update
        require_once(dirname(__FILE__).'/services/StockPriceSenderService.php');
        $sender = new StockPriceSenderService();
        $sender->processPriceUpdate($product, (int)$specificPrice->id_product_attribute);
    }
    
    /**
     * Hook for product object updates
     */
    public function hookActionObjectProductUpdateAfter($params)
    {
        // Only for main shop
        if (Configuration::get('STOCKPRICESYNC_SHOP_TYPE') != 'MAIN' || 
            !Configuration::get('STOCKPRICESYNC_REAL_TIME_SYNC')) {
            return;
        }
        
        // Get the product object
        if (!isset($params['object']) || !($params['object'] instanceof Product)) {
            return;
        }
        
        $product = $params['object'];
        
        // Skip products without reference
        if (empty($product->reference)) {
            return;
        }
        
        // Process stock update if enabled
        if (Configuration::get('STOCKPRICESYNC_SYNC_STOCK')) {
            $id_product = (int)$product->id;
            $quantity = StockAvailable::getQuantityAvailableByProduct($id_product, 0);
            
            require_once(dirname(__FILE__).'/services/StockPriceSenderService.php');
            $sender = new StockPriceSenderService();
            $sender->processStockUpdate($product->reference, null, $quantity);
        }
        
        // Process price update if enabled
        if (Configuration::get('STOCKPRICESYNC_SYNC_PRICE')) {
            require_once(dirname(__FILE__).'/services/StockPriceSenderService.php');
            $sender = new StockPriceSenderService();
            $sender->processPriceUpdate($product);
        }
    }
    
    /**
     * Hook for StockAvailable updates
     */
    public function hookActionObjectStockAvailableUpdateAfter($params)
    {
        // Only for main shop
        if (Configuration::get('STOCKPRICESYNC_SHOP_TYPE') != 'MAIN' || 
            !Configuration::get('STOCKPRICESYNC_SYNC_STOCK') || 
            !Configuration::get('STOCKPRICESYNC_REAL_TIME_SYNC')) {
            return;
        }
        
        // Get the StockAvailable object
        if (!isset($params['object']) || !($params['object'] instanceof StockAvailable)) {
            return;
        }
        
        $stock = $params['object'];
        $id_product = (int)$stock->id_product;
        $id_product_attribute = (int)$stock->id_product_attribute;
        
        // Get product reference
        $product = new Product($id_product);
        if (!Validate::isLoadedObject($product) || empty($product->reference)) {
            return; // Skip products without reference
        }
        
        // Get combination reference if needed
        $combination_reference = null;
        if ($id_product_attribute > 0) {
            $combination = new Combination($id_product_attribute);
            if (Validate::isLoadedObject($combination) && !empty($combination->reference)) {
                $combination_reference = $combination->reference;
            }
        }
        
        // Process stock update
        require_once(dirname(__FILE__).'/services/StockPriceSenderService.php');
        $sender = new StockPriceSenderService();
        $sender->processStockUpdate($product->reference, $combination_reference, (int)$stock->quantity);
    }
    
    /**
     * Hook for back-office header
     */
    public function hookDisplayBackOfficeHeader()
    {
        // Add notification if the module is not configured
        if (empty(Configuration::get('STOCKPRICESYNC_SHOP_TYPE'))) {
            $this->context->controller->warnings[] = $this->l('Stock & Price Synchronizer module is not configured yet. Please configure it for proper operation.');
        }
    }
    
    /**
     * Hook for admin controller media
     */
    public function hookActionAdminControllerSetMedia()
    {
        // Add JavaScript for the module
        $this->context->controller->addJS($this->_path . 'views/js/admin.js');
        $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
    }
	/**
 * Export logs to CSV based on filters
 */
private function exportLogsToCSV($filters = [])
{
    // Get logs with filters
    $logs = $this->getFilteredLogs($filters, 10000, 0);
    
    if (empty($logs)) {
        return false;
    }
    
    // Prepare CSV headers
    $headers = [
        $this->l('ID'), 
        $this->l('Date'), 
        $this->l('Shop'), 
        $this->l('Type'), 
        $this->l('Product Reference'), 
        $this->l('Combination Reference'),
        $this->l('Old Quantity'),
        $this->l('New Quantity'),
        $this->l('Old Price'),
        $this->l('New Price'),
        $this->l('Status'),
        $this->l('Log Level'),
        $this->l('Message')
    ];
    
    // Create CSV content
    $content = implode(';', $headers) . "\n";
    
    foreach ($logs as $log) {
        // Format shop name
        $shop_name = isset($log['shop_name']) ? $log['shop_name'] : '';
        if (empty($shop_name) && isset($log['id_shop_remote'])) {
            $shop_name = $this->l('Shop ID:') . ' ' . $log['id_shop_remote'];
        } elseif (empty($shop_name) && !isset($log['id_shop_remote'])) {
            $shop_name = $this->l('This shop');
        }
        
        // Format status
        $status = (int)$log['status'] ? $this->l('Success') : $this->l('Error');
        
        // Format log level
        $log_level = '';
        if (isset($log['log_level'])) {
            switch ((int)$log['log_level']) {
                case 1:
                    $log_level = $this->l('Info');
                    break;
                case 2:
                    $log_level = $this->l('Warning');
                    break;
                case 3:
                    $log_level = $this->l('Error');
                    break;
                default:
                    $log_level = $log['log_level'];
            }
        } else {
            $log_level = $this->l('Info');
        }
        
        // Row data
        $row = [
            $log['id_log'],
            $log['date_add'],
            $shop_name,
            $log['sync_type'],
            isset($log['product_reference']) ? $log['product_reference'] : '',
            isset($log['combination_reference']) ? $log['combination_reference'] : '',
            isset($log['quantity_old']) ? $log['quantity_old'] : '',
            isset($log['quantity_new']) ? $log['quantity_new'] : '',
            isset($log['price_old']) ? number_format($log['price_old'], 6, '.', '') : '',
            isset($log['price_new']) ? number_format($log['price_new'], 6, '.', '') : '',
            $status,
            $log_level,
            isset($log['message']) ? $log['message'] : ''
        ];
        
        // Add CSV row - escape fields and ensure proper encoding
        $csvRow = [];
        foreach ($row as $field) {
            $csvRow[] = '"' . str_replace('"', '""', $field) . '"';
        }
        
        $content .= implode(';', $csvRow) . "\n";
    }
    
    // Generate filename
    $filename = 'stockpricesync_logs_' . date('Y-m-d_His') . '.csv';
    
    // Headers for download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    // Output
    echo $content;
    exit;
}

/**
 * Get filtered logs
 */
private function getFilteredLogs($filters = [], $limit = 100, $offset = 0)
{
    // Load the SyncLog class if needed
    require_once(_PS_MODULE_DIR_ . 'stockpricesync/classes/SyncLog.php');
    
    return SyncLog::getFilteredLogs($filters, $limit, $offset);
}

/**
 * Count filtered logs
 */
private function countFilteredLogs($filters = [])
{
    // Load the SyncLog class if needed
    require_once(_PS_MODULE_DIR_ . 'stockpricesync/classes/SyncLog.php');
    
    return SyncLog::countFilteredLogs($filters);
}

/**
 * Get logs statistics
 */
private function getLogsStatistics($filters = [])
{
    $stats = [
        'total' => 0,
        'success' => 0,
        'error' => 0,
        'stock_syncs' => 0,
        'price_syncs' => 0,
        'success_rate' => 0
    ];
    
    // Load the SyncLog class if needed
    require_once(_PS_MODULE_DIR_ . 'stockpricesync/classes/SyncLog.php');
    
    // Count total logs
    $stats['total'] = SyncLog::countFilteredLogs($filters);
    
    // Success count
    $successFilters = $filters;
    $successFilters['status'] = 1;
    $stats['success'] = SyncLog::countFilteredLogs($successFilters);
    
    // Error count
    $errorFilters = $filters;
    $errorFilters['status'] = 0;
    $stats['error'] = SyncLog::countFilteredLogs($errorFilters);
    
    // Stock syncs
    $stockFilters = $filters;
    $stockFilters['sync_type'] = 'stock';
    $stats['stock_syncs'] = SyncLog::countFilteredLogs($stockFilters);
    
    // Price syncs
    $priceFilters = $filters;
    $priceFilters['sync_type'] = 'price';
    $stats['price_syncs'] = SyncLog::countFilteredLogs($priceFilters);
    
    // Calculate success rate
    if ($stats['total'] > 0) {
        $stats['success_rate'] = round(($stats['success'] / $stats['total']) * 100);
    }
    
    return $stats;
}
/**
 * Hook for product updates
 */
public function hookActionProductUpdate($params)
{
    // Only for main shop
    if (Configuration::get('STOCKPRICESYNC_SHOP_TYPE') != 'MAIN' || 
        !Configuration::get('STOCKPRICESYNC_REAL_TIME_SYNC')) {
        return;
    }
    
    // Get the product object
    if (!isset($params['product']) || !($params['product'] instanceof Product)) {
        return;
    }
    
    $product = $params['product'];
    
    // Skip products without reference
    if (empty($product->reference)) {
        return;
    }
    
    // Process stock update if enabled
    if (Configuration::get('STOCKPRICESYNC_SYNC_STOCK')) {
        $id_product = (int)$product->id;
        $quantity = StockAvailable::getQuantityAvailableByProduct($id_product, 0);
        
        require_once(dirname(__FILE__).'/services/StockPriceSenderService.php');
        $sender = new StockPriceSenderService();
        $sender->processStockUpdate($product->reference, null, $quantity);
    }
    
    // Process price update if enabled
    if (Configuration::get('STOCKPRICESYNC_SYNC_PRICE')) {
        require_once(dirname(__FILE__).'/services/StockPriceSenderService.php');
        $sender = new StockPriceSenderService();
        $sender->processPriceUpdate($product);
    }
}
}