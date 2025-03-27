<?php
/**
 * Remote Shop Class for StockPriceSync
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class SPSRemoteShop extends ObjectModel
{
    public $id_shop_remote;
    public $name;
    public $url;
    public $api_key;
    public $active;
    public $sync_stock;
    public $sync_price;
    public $price_percentage;
    public $date_add;
    public $date_upd;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'stockpricesync_shop',
        'primary' => 'id_shop_remote',
        'fields' => [
            'name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 255],
            'url' => ['type' => self::TYPE_STRING, 'validate' => 'isUrl', 'required' => true, 'size' => 255],
            'api_key' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 255],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'required' => true],
            'sync_stock' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'required' => true],
            'sync_price' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'required' => true],
            'price_percentage' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat', 'required' => true],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true],
        ],
    ];


    /**
     * Get active shops with stock sync enabled
     */
    public static function getActiveShopsWithStockSync()
    {
        $query = new DbQuery();
        $query->select('*');
        $query->from('stockpricesync_shop');
        $query->where('active = 1');
        $query->where('sync_stock = 1');
        
        return Db::getInstance()->executeS($query) ?: [];
    }

    /**
     * Get active shops with price sync enabled
     */
    public static function getActiveShopsWithPriceSync()
    {
        $query = new DbQuery();
        $query->select('*');
        $query->from('stockpricesync_shop');
        $query->where('active = 1');
        $query->where('sync_price = 1');
        
        return Db::getInstance()->executeS($query) ?: [];
    }

    /**
     * Get all active shops
     */
    public static function getActiveShops()
    {
        $query = new DbQuery();
        $query->select('*');
        $query->from('stockpricesync_shop');
        $query->where('active = 1');
        
        return Db::getInstance()->executeS($query) ?: [];
    }
    
    /**
     * Get shop by API key
     */
    public static function getByApiKey($api_key)
    {
        $query = new DbQuery();
        $query->select('id_shop_remote');
        $query->from('stockpricesync_shop');
        $query->where('api_key = "' . pSQL($api_key) . '"');
        
        $id_shop_remote = Db::getInstance()->getValue($query);
        
        if ($id_shop_remote) {
            return new SPSRemoteShop((int)$id_shop_remote);
        }
        
        return false;
    }
    
    /**
     * Check if API key belongs to an inactive shop
     */
    public static function isApiKeyForInactiveShop($api_key)
    {
        $query = new DbQuery();
        $query->select('id_shop_remote');
        $query->from('stockpricesync_shop');
        $query->where('api_key = "' . pSQL($api_key) . '"');
        $query->where('active = 0');
        
        return (bool)Db::getInstance()->getValue($query);
    }
}