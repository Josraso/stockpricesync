<?php
/**
 * Sync Log Class for StockPriceSync
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class SyncLog extends ObjectModel
{
    public $id_log;
    public $id_shop_remote;
    public $sync_type;
    public $product_reference;
    public $combination_reference;
    public $quantity_old;
    public $quantity_new;
    public $price_old;
    public $price_new;
    public $status;
    public $message;
    public $log_level;
    public $date_add;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'stockpricesync_log',
        'primary' => 'id_log',
        'fields' => [
            'id_shop_remote' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'sync_type' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true],
            'product_reference' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 64],
            'combination_reference' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 64],
            'quantity_old' => ['type' => self::TYPE_INT, 'validate' => 'isInt'],
            'quantity_new' => ['type' => self::TYPE_INT, 'validate' => 'isInt'],
            'price_old' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice'],
            'price_new' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice'],
            'status' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'required' => true],
            'message' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName'],
            'log_level' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true],
        ],
    ];

    /**
     * Get filtered logs
     */
    public static function getFilteredLogs($filters = [], $limit = 100, $offset = 0)
    {
        $sql = new DbQuery();
        $sql->select('l.*, s.name as shop_name');
        $sql->from('stockpricesync_log', 'l');
        $sql->leftJoin('stockpricesync_shop', 's', 'l.id_shop_remote = s.id_shop_remote');
        
        // Apply filters
        if (!empty($filters)) {
            if (isset($filters['date_from']) && !empty($filters['date_from'])) {
                $sql->where('l.date_add >= "'.pSQL($filters['date_from']).' 00:00:00"');
            }
            
            if (isset($filters['date_to']) && !empty($filters['date_to'])) {
                $sql->where('l.date_add <= "'.pSQL($filters['date_to']).' 23:59:59"');
            }
            
            if (isset($filters['sync_type']) && !empty($filters['sync_type'])) {
                $sql->where('l.sync_type = "'.pSQL($filters['sync_type']).'"');
            }
            
            if (isset($filters['status']) && $filters['status'] !== '') {
                $sql->where('l.status = '.(int)$filters['status']);
            }
            
            if (isset($filters['shop_id']) && !empty($filters['shop_id'])) {
                $sql->where('l.id_shop_remote = '.(int)$filters['shop_id']);
            }
            
            if (isset($filters['product_reference']) && !empty($filters['product_reference'])) {
                $sql->where('l.product_reference LIKE "%'.pSQL($filters['product_reference']).'%"');
            }
            
            if (isset($filters['log_level']) && $filters['log_level'] !== '') {
                $sql->where('l.log_level = '.(int)$filters['log_level']);
            }
            
            if (isset($filters['search']) && !empty($filters['search'])) {
                $search = pSQL($filters['search']);
                $sql->where('(l.product_reference LIKE "%'.$search.'%" OR l.combination_reference LIKE "%'.$search.'%" OR l.message LIKE "%'.$search.'%")');
            }
        }
        
        $sql->orderBy('l.date_add DESC');
        $sql->limit($limit, $offset);
        
        return Db::getInstance()->executeS($sql);
    }
    
    /**
     * Count filtered logs
     */
    public static function countFilteredLogs($filters = [])
    {
        $sql = new DbQuery();
        $sql->select('COUNT(*)');
        $sql->from('stockpricesync_log', 'l');
        
        // Apply filters
        if (!empty($filters)) {
            if (isset($filters['date_from']) && !empty($filters['date_from'])) {
                $sql->where('l.date_add >= "'.pSQL($filters['date_from']).' 00:00:00"');
            }
            
            if (isset($filters['date_to']) && !empty($filters['date_to'])) {
                $sql->where('l.date_add <= "'.pSQL($filters['date_to']).' 23:59:59"');
            }
            
            if (isset($filters['sync_type']) && !empty($filters['sync_type'])) {
                $sql->where('l.sync_type = "'.pSQL($filters['sync_type']).'"');
            }
            
            if (isset($filters['status']) && $filters['status'] !== '') {
                $sql->where('l.status = '.(int)$filters['status']);
            }
            
            if (isset($filters['shop_id']) && !empty($filters['shop_id'])) {
                $sql->where('l.id_shop_remote = '.(int)$filters['shop_id']);
            }
            
            if (isset($filters['product_reference']) && !empty($filters['product_reference'])) {
                $sql->where('l.product_reference LIKE "%'.pSQL($filters['product_reference']).'%"');
            }
            
            if (isset($filters['log_level']) && $filters['log_level'] !== '') {
                $sql->where('l.log_level = '.(int)$filters['log_level']);
            }
            
            if (isset($filters['search']) && !empty($filters['search'])) {
                $search = pSQL($filters['search']);
                $sql->where('(l.product_reference LIKE "%'.$search.'%" OR l.combination_reference LIKE "%'.$search.'%" OR l.message LIKE "%'.$search.'%")');
            }
        }
        
        return (int)Db::getInstance()->getValue($sql);
    }
}