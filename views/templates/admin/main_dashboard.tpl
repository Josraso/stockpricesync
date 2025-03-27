{*
* Main Shop Dashboard for Stock & Price Synchronizer
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-dashboard"></i> {l s='Stock & Price Synchronizer - Main Store Dashboard' mod='stockpricesync'}
        <a href="#" id="reset_shop_type_btn" class="btn btn-xs btn-default pull-right">{l s='Change Store Type' mod='stockpricesync'}</a>
    </div>
    
    {* General configuration panel *}
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-cog"></i> {l s='General Configuration' mod='stockpricesync'}
        </div>
        
        <form class="form-horizontal" action="{$current_link}" method="post">
            <div class="form-group">
                <label class="control-label col-lg-3">{l s='Stock synchronization:' mod='stockpricesync'}</label>
                <div class="col-lg-9">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="STOCKPRICESYNC_SYNC_STOCK" id="STOCKPRICESYNC_SYNC_STOCK_on" value="1" {if $sync_stock}checked="checked"{/if}>
                        <label for="STOCKPRICESYNC_SYNC_STOCK_on">{l s='Yes' mod='stockpricesync'}</label>
                        <input type="radio" name="STOCKPRICESYNC_SYNC_STOCK" id="STOCKPRICESYNC_SYNC_STOCK_off" value="0" {if !$sync_stock}checked="checked"{/if}>
                        <label for="STOCKPRICESYNC_SYNC_STOCK_off">{l s='No' mod='stockpricesync'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                    <p class="help-block">
                        {l s='Enable stock synchronization with child stores.' mod='stockpricesync'}
                    </p>
                </div>
            </div>
            
            <div class="form-group">
                <label class="control-label col-lg-3">{l s='Price synchronization:' mod='stockpricesync'}</label>
                <div class="col-lg-9">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="STOCKPRICESYNC_SYNC_PRICE" id="STOCKPRICESYNC_SYNC_PRICE_on" value="1" {if $sync_price}checked="checked"{/if}>
                        <label for="STOCKPRICESYNC_SYNC_PRICE_on">{l s='Yes' mod='stockpricesync'}</label>
                        <input type="radio" name="STOCKPRICESYNC_SYNC_PRICE" id="STOCKPRICESYNC_SYNC_PRICE_off" value="0" {if !$sync_price}checked="checked"{/if}>
                        <label for="STOCKPRICESYNC_SYNC_PRICE_off">{l s='No' mod='stockpricesync'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                    <p class="help-block">
                        {l s='Enable price synchronization with child stores.' mod='stockpricesync'}
                    </p>
                </div>
            </div>
            
            <div class="form-group">
                <label class="control-label col-lg-3">{l s='Real-time synchronization:' mod='stockpricesync'}</label>
                <div class="col-lg-9">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="STOCKPRICESYNC_REAL_TIME_SYNC" id="STOCKPRICESYNC_REAL_TIME_SYNC_on" value="1" {if $real_time_sync}checked="checked"{/if}>
                        <label for="STOCKPRICESYNC_REAL_TIME_SYNC_on">{l s='Yes' mod='stockpricesync'}</label>
                        <input type="radio" name="STOCKPRICESYNC_REAL_TIME_SYNC" id="STOCKPRICESYNC_REAL_TIME_SYNC_off" value="0" {if !$real_time_sync}checked="checked"{/if}>
                        <label for="STOCKPRICESYNC_REAL_TIME_SYNC_off">{l s='No' mod='stockpricesync'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                    <p class="help-block">
                        {l s='Synchronize stock and price changes immediately. If disabled, updates must be sent manually.' mod='stockpricesync'}
                    </p>
                </div>
            </div>
            
            <div class="form-group">
                <label class="control-label col-lg-3">{l s='Batch size:' mod='stockpricesync'}</label>
                <div class="col-lg-9">
                    <input type="number" name="STOCKPRICESYNC_BATCH_SIZE" value="{$batch_size}" class="form-control fixed-width-sm" min="10" max="500">
                    <p class="help-block">
                        {l s='Number of products to process at once. Smaller values reduce server load but increase sync time.' mod='stockpricesync'}
                    </p>
                </div>
            </div>
            
            <div class="panel-footer">
                <button type="submit" name="submit_main_config" class="btn btn-default pull-right">
                    <i class="process-icon-save"></i> {l s='Save' mod='stockpricesync'}
                </button>
            </div>
        </form>
    </div>
    
    {* Stats and quick actions *}
    <div class="row">
        <div class="col-lg-4">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-link"></i> {l s='Connected Stores' mod='stockpricesync'}
                </div>
                <div class="panel-body text-center">
                    <h1>{if isset($shops)}{count($shops)}{else}0{/if}</h1>
                    <a href="{$current_link}&view_shops=1" class="btn btn-default">
                        <i class="icon-list"></i> {l s='Manage Stores' mod='stockpricesync'}
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-refresh"></i> {l s='Pending Syncs' mod='stockpricesync'}
                </div>
                <div class="panel-body text-center">
                    <h1>{if isset($pending_syncs)}{$pending_syncs}{else}0{/if}</h1>
                    <a href="{$current_link}&view_logs=1" class="btn btn-default">
                        <i class="icon-list-alt"></i> {l s='View Sync Logs' mod='stockpricesync'}
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-bolt"></i> {l s='Quick Actions' mod='stockpricesync'}
                </div>
                <div class="panel-body text-center">
                    <form method="post" action="{$current_link}">
                        <button type="submit" name="sync_all_shops" class="btn btn-primary" onclick="return confirm('{l s='Are you sure you want to sync ALL stock and prices to ALL stores? This might take some time.' mod='stockpricesync' js=1}');">
                            <i class="icon-refresh"></i> {l s='Sync All Stores Now' mod='stockpricesync'}
                        </button>
                        <select name="sync_type" class="form-control margin-top-10">
                            <option value="both">{l s='Stock & Prices' mod='stockpricesync'}</option>
                            <option value="stock">{l s='Stock Only' mod='stockpricesync'}</option>
                            <option value="price">{l s='Prices Only' mod='stockpricesync'}</option>
                        </select>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    {* Connected stores table *}
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-globe"></i> {l s='Connected Stores' mod='stockpricesync'}
            <span class="badge">{if isset($shops)}{count($shops)}{else}0{/if}</span>
            <a href="{$current_link}&view_shops=1" class="btn btn-default pull-right">
    <i class="icon-plus"></i> {l s='Add New Store' mod='stockpricesync'}
</a>
        </div>
        
        {if isset($shops) && $shops}
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{l s='Name' mod='stockpricesync'}</th>
                            <th>{l s='URL' mod='stockpricesync'}</th>
                            <th>{l s='Status' mod='stockpricesync'}</th>
                            <th>{l s='Stock Sync' mod='stockpricesync'}</th>
                            <th>{l s='Price Sync' mod='stockpricesync'}</th>
                            <th>{l s='Price Adjustment' mod='stockpricesync'}</th>
                            <th>{l s='Actions' mod='stockpricesync'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$shops item=shop}
                            <tr>
                                <td>{$shop.name|escape:'html':'UTF-8'}</td>
                                <td>
                                    <a href="{$shop.url|escape:'html':'UTF-8'}" target="_blank">{$shop.url|escape:'html':'UTF-8'}</a>
                                </td>
                                <td>
                                    {if $shop.active}
                                        <span class="badge badge-success">{l s='Active' mod='stockpricesync'}</span>
                                    {else}
                                        <span class="badge badge-danger">{l s='Inactive' mod='stockpricesync'}</span>
                                    {/if}
                                </td>
                                <td>
                                    {if $shop.sync_stock}
                                        <span class="badge badge-success">{l s='Enabled' mod='stockpricesync'}</span>
                                    {else}
                                        <span class="badge badge-danger">{l s='Disabled' mod='stockpricesync'}</span>
                                    {/if}
                                </td>
                                <td>
                                    {if $shop.sync_price}
                                        <span class="badge badge-success">{l s='Enabled' mod='stockpricesync'}</span>
                                    {else}
                                        <span class="badge badge-danger">{l s='Disabled' mod='stockpricesync'}</span>
                                    {/if}
                                </td>
                                <td>
                                    {if $shop.price_percentage > 0}
                                        <span class="badge badge-info">+{$shop.price_percentage}%</span>
                                    {elseif $shop.price_percentage < 0}
                                        <span class="badge badge-warning">{$shop.price_percentage}%</span>
                                    {else}
                                        <span class="badge badge-default">0%</span>
                                    {/if}
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{$current_link}&edit_shop=1&id_shop_remote={$shop.id_shop_remote}" class="btn btn-default btn-xs">
                                            <i class="icon-pencil"></i> {l s='Edit' mod='stockpricesync'}
                                        </a>
                                        <button type="button" class="btn btn-default btn-xs dropdown-toggle" data-toggle="dropdown">
                                            <i class="icon-caret-down"></i>
                                        </button>
                                        <ul class="dropdown-menu pull-right">
                                            <li>
                                                <a href="{$current_link}&view_shop=1&id_shop_remote={$shop.id_shop_remote}">
                                                    <i class="icon-eye"></i> {l s='View Details' mod='stockpricesync'}
                                                </a>
                                            </li>
                                            <li>
                                                <a href="{$current_link}&sync_shop=1&id_shop_remote={$shop.id_shop_remote}" onclick="return confirm('{l s='Are you sure you want to sync ALL stock and prices to this store?' mod='stockpricesync' js=1}');">
                                                    <i class="icon-refresh"></i> {l s='Sync Now' mod='stockpricesync'}
                                                </a>
                                            </li>
                                            <li>
                                                <a href="{$current_link}&generate_api_key=1&id_shop_remote={$shop.id_shop_remote}" onclick="return confirm('{l s='Are you sure you want to generate a new API key? This will invalidate the current one.' mod='stockpricesync' js=1}');">
                                                    <i class="icon-key"></i> {l s='Generate New API Key' mod='stockpricesync'}
                                                </a>
                                            </li>
                                            <li class="divider"></li>
                                            <li>
                                                <a href="{$current_link}&delete_shop=1&id_shop_remote={$shop.id_shop_remote}" onclick="return confirm('{l s='Are you sure you want to delete this store? This action cannot be undone.' mod='stockpricesync' js=1}');" class="text-danger">
                                                    <i class="icon-trash"></i> {l s='Delete' mod='stockpricesync'}
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        {else}
            <div class="alert alert-warning">
                <p>{l s='No stores connected yet. Add a child store to start synchronizing stock and prices.' mod='stockpricesync'}</p>
                <a href="{$current_link}&view_shops=1" class="btn btn-default">
                    <i class="icon-plus"></i> {l s='Add New Store' mod='stockpricesync'}
                </a>
            </div>
        {/if}
    </div>
    
    {* Recent sync logs *}
    {if isset($recent_logs) && $recent_logs}
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-history"></i> {l s='Recent Synchronization Activity' mod='stockpricesync'}
                <a href="{$current_link}&view_logs=1" class="btn btn-default btn-xs pull-right">
                    <i class="icon-list-alt"></i> {l s='View All Logs' mod='stockpricesync'}
                </a>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{l s='Date' mod='stockpricesync'}</th>
                            <th>{l s='Store' mod='stockpricesync'}</th>
                            <th>{l s='Type' mod='stockpricesync'}</th>
                            <th>{l s='Product' mod='stockpricesync'}</th>
                            <th>{l s='Status' mod='stockpricesync'}</th>
                            <th>{l s='Message' mod='stockpricesync'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$recent_logs item=log}
                            <tr>
                                <td>{$log.date_add|date_format:"%Y-%m-%d %H:%M:%S"}</td>
                                <td>{$log.shop_name|escape:'html':'UTF-8'}</td>
                                <td>
                                    {if $log.sync_type == 'stock'}
                                        <span class="badge badge-info">{l s='Stock' mod='stockpricesync'}</span>
                                    {elseif $log.sync_type == 'price'}
                                        <span class="badge badge-primary">{l s='Price' mod='stockpricesync'}</span>
                                    {else}
                                        <span class="badge badge-default">{l s='Both' mod='stockpricesync'}</span>
                                    {/if}
                                </td>
                                <td>
                                    {if $log.product_reference}
                                        {$log.product_reference|escape:'html':'UTF-8'}
                                        {if $log.combination_reference}
                                            <br><small>{$log.combination_reference|escape:'html':'UTF-8'}</small>
                                        {/if}
                                    {else}
                                        -
                                    {/if}
                                </td>
                                <td>
                                    {if $log.status}
                                        <span class="badge badge-success">{l s='Success' mod='stockpricesync'}</span>
                                    {else}
                                        <span class="badge badge-danger">{l s='Error' mod='stockpricesync'}</span>
                                    {/if}
                                </td>
                                <td>{$log.message|truncate:50|escape:'html':'UTF-8'}</td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
    {/if}
</div>

<form id="reset_type_form" action="{$current_link}" method="post" style="display:none;">
    <input type="hidden" name="submit_shop_type" value="1">
    <input type="hidden" name="STOCKPRICESYNC_SHOP_TYPE" value="">
</form>

<script type="text/javascript">
    $(document).ready(function() {
        // Reset shop type button
        $("#reset_shop_type_btn").click(function(e) {
            e.preventDefault();
            if (confirm('{l s="WARNING! Changing the store type will delete ALL previous synchronization data. This operation cannot be undone. Are you ABSOLUTELY SURE you want to continue?" mod='stockpricesync' js=1}')) {
                $("#reset_type_form").submit();
            }
        });
    });
</script>