{*
* Shop Details View for Stock & Price Synchronizer
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-shopping-cart"></i> 
        {if isset($edit_shop)}
            {l s='Edit Remote Shop - Stock & Price Synchronizer' mod='stockpricesync'}
        {else}
            {l s='Remote Shop Details - Stock & Price Synchronizer' mod='stockpricesync'}
        {/if}
        <a href="{$current_link}" class="btn btn-default btn-xs pull-right">
            <i class="icon-arrow-left"></i> {l s='Back to Dashboard' mod='stockpricesync'}
        </a>
    </div>
    
    {if isset($edit_shop)}
        {* Edit shop form *}
        <form class="form-horizontal" action="{$current_link}" method="post">
            <input type="hidden" name="id_shop_remote" value="{$edit_shop->id_shop_remote}">
            
            <div class="form-group">
                <label class="control-label col-lg-3 required">{l s='Shop name:' mod='stockpricesync'}</label>
                <div class="col-lg-6">
                    <input type="text" name="name" value="{$edit_shop->name|escape:'html':'UTF-8'}" class="form-control" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="control-label col-lg-3 required">{l s='Shop URL:' mod='stockpricesync'}</label>
                <div class="col-lg-6">
                    <input type="url" name="url" value="{$edit_shop->url|escape:'html':'UTF-8'}" class="form-control" required placeholder="https://example.com">
                </div>
            </div>
            
            <div class="form-group">
                <label class="control-label col-lg-3 required">{l s='API Key:' mod='stockpricesync'}</label>
                <div class="col-lg-6">
                    <div class="input-group">
                        <input type="text" name="api_key" value="{$edit_shop->api_key|escape:'html':'UTF-8'}" class="form-control" readonly id="api_key">
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-default" id="copy_api_key">
                                <i class="icon-copy"></i> {l s='Copy' mod='stockpricesync'}
                            </button>
                        </span>
                    </div>
                    <p class="help-block">
                        {l s='API key for this remote shop. Use this key in the remote shop configuration.' mod='stockpricesync'}
                        <a href="{$current_link}&generate_api_key=1&id_shop_remote={$edit_shop->id_shop_remote}" class="btn btn-xs btn-warning" onclick="return confirm('{l s='Are you sure you want to generate a new API key? This will invalidate the current one.' mod='stockpricesync' js=1}');">
                            <i class="icon-refresh"></i> {l s='Generate New Key' mod='stockpricesync'}
                        </a>
                    </p>
                </div>
            </div>
            
            <div class="form-group">
                <label class="control-label col-lg-3">{l s='Status:' mod='stockpricesync'}</label>
                <div class="col-lg-6">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="active" id="active_on" value="1" {if $edit_shop->active}checked="checked"{/if}>
                        <label for="active_on">{l s='Active' mod='stockpricesync'}</label>
                        <input type="radio" name="active" id="active_off" value="0" {if !$edit_shop->active}checked="checked"{/if}>
                        <label for="active_off">{l s='Inactive' mod='stockpricesync'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                    <p class="help-block">
                        {l s='Enable or disable synchronization with this shop.' mod='stockpricesync'}
                    </p>
                </div>
            </div>
            
            <div class="form-group">
                <label class="control-label col-lg-3">{l s='Sync stock:' mod='stockpricesync'}</label>
                <div class="col-lg-6">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="sync_stock" id="sync_stock_on" value="1" {if $edit_shop->sync_stock}checked="checked"{/if}>
                        <label for="sync_stock_on">{l s='Yes' mod='stockpricesync'}</label>
                        <input type="radio" name="sync_stock" id="sync_stock_off" value="0" {if !$edit_shop->sync_stock}checked="checked"{/if}>
                        <label for="sync_stock_off">{l s='No' mod='stockpricesync'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                    <p class="help-block">
                        {l s='Enable or disable stock synchronization with this shop.' mod='stockpricesync'}
                    </p>
                </div>
            </div>
            
            <div class="form-group">
                <label class="control-label col-lg-3">{l s='Sync price:' mod='stockpricesync'}</label>
                <div class="col-lg-6">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="sync_price" id="sync_price_on" value="1" {if $edit_shop->sync_price}checked="checked"{/if}>
                        <label for="sync_price_on">{l s='Yes' mod='stockpricesync'}</label>
                        <input type="radio" name="sync_price" id="sync_price_off" value="0" {if !$edit_shop->sync_price}checked="checked"{/if}>
                        <label for="sync_price_off">{l s='No' mod='stockpricesync'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                    <p class="help-block">
                        {l s='Enable or disable price synchronization with this shop.' mod='stockpricesync'}
                    </p>
                </div>
            </div>
            
            <div class="form-group">
                <label class="control-label col-lg-3">{l s='Price adjustment:' mod='stockpricesync'}</label>
                <div class="col-lg-2">
                    <div class="input-group">
                        <span class="input-group-addon">%</span>
                        <input type="number" name="price_percentage" step="0.01" value="{$edit_shop->price_percentage}" class="form-control">
                    </div>
                </div>
                <div class="col-lg-7">
                    <p class="help-block">
                        {l s='Percentage to adjust prices when syncing to this shop. Use positive values to increase prices, negative to decrease.' mod='stockpricesync'}
                    </p>
                    <div class="alert alert-info price-preview" id="price_preview">
                        {l s='Example:' mod='stockpricesync'} {l s='A product costing' mod='stockpricesync'} <strong>100€</strong> {l s='will be synchronized at' mod='stockpricesync'} <strong><span id="price_preview_value">100</span>€</strong>
                    </div>
                </div>
            </div>
            
            <div class="panel-footer">
                <button type="submit" name="submit_edit_shop" class="btn btn-default pull-right">
                    <i class="process-icon-save"></i> {l s='Save' mod='stockpricesync'}
                </button>
            </div>
        </form>
    {elseif isset($view_shop)}
        {* View shop details *}
        <div class="row">
            <div class="col-lg-6">
                <div class="panel">
                    <div class="panel-heading">
                        <i class="icon-info-circle"></i> {l s='Shop Information' mod='stockpricesync'}
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <tr>
                                <th width="30%">{l s='Name:' mod='stockpricesync'}</th>
                                <td>{$view_shop->name|escape:'html':'UTF-8'}</td>
                            </tr>
                            <tr>
                                <th>{l s='URL:' mod='stockpricesync'}</th>
                                <td><a href="{$view_shop->url|escape:'html':'UTF-8'}" target="_blank">{$view_shop->url|escape:'html':'UTF-8'}</a></td>
                            </tr>
                            <tr>
                                <th>{l s='API Key:' mod='stockpricesync'}</th>
                                <td>
                                    <div class="input-group">
                                        <input type="text" value="{$view_shop->api_key|escape:'html':'UTF-8'}" class="form-control" readonly id="api_key">
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-default" id="copy_api_key">
                                                <i class="icon-copy"></i> {l s='Copy' mod='stockpricesync'}
                                            </button>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>{l s='Status:' mod='stockpricesync'}</th>
                                <td>
                                    {if $view_shop->active}
                                        <span class="badge badge-success">{l s='Active' mod='stockpricesync'}</span>
                                    {else}
                                        <span class="badge badge-danger">{l s='Inactive' mod='stockpricesync'}</span>
                                    {/if}
                                </td>
                            </tr>
                            <tr>
                                <th>{l s='Stock Sync:' mod='stockpricesync'}</th>
                                <td>
                                    {if $view_shop->sync_stock}
                                        <span class="badge badge-success">{l s='Enabled' mod='stockpricesync'}</span>
                                    {else}
                                        <span class="badge badge-danger">{l s='Disabled' mod='stockpricesync'}</span>
                                    {/if}
                                </td>
                            </tr>
                            <tr>
                                <th>{l s='Price Sync:' mod='stockpricesync'}</th>
                                <td>
                                    {if $view_shop->sync_price}
                                        <span class="badge badge-success">{l s='Enabled' mod='stockpricesync'}</span>
                                    {else}
                                        <span class="badge badge-danger">{l s='Disabled' mod='stockpricesync'}</span>
                                    {/if}
                                </td>
                            </tr>
                            <tr>
                                <th>{l s='Price Adjustment:' mod='stockpricesync'}</th>
                                <td>
                                    {if $view_shop->price_percentage > 0}
                                        <span class="badge badge-info">+{$view_shop->price_percentage}%</span>
                                    {elseif $view_shop->price_percentage < 0}
                                        <span class="badge badge-warning">{$view_shop->price_percentage}%</span>
                                    {else}
                                        <span class="badge badge-default">0%</span>
                                    {/if}
                                </td>
                            </tr>
                            <tr>
                                <th>{l s='Added:' mod='stockpricesync'}</th>
                                <td>{$view_shop->date_add|date_format:"%Y-%m-%d %H:%M:%S"}</td>
                            </tr>
                            <tr>
                                <th>{l s='Last Update:' mod='stockpricesync'}</th>
                                <td>{$view_shop->date_upd|date_format:"%Y-%m-%d %H:%M:%S"}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="panel">
                    <div class="panel-heading">
                        <i class="icon-dashboard"></i> {l s='Synchronization Statistics' mod='stockpricesync'}
                    </div>
                    
                    {if isset($shop_stats)}
                        <div class="row text-center">
                            <div class="col-xs-4">
                                <div class="panel widget">
                                    <div class="widget-heading">{l s='Total Syncs' mod='stockpricesync'}</div>
                                    <div class="widget-body">
                                        <span class="stat-value">{$shop_stats.stock_syncs + $shop_stats.price_syncs}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xs-4">
                                <div class="panel widget">
                                    <div class="widget-heading">{l s='Stock Syncs' mod='stockpricesync'}</div>
                                    <div class="widget-body">
                                        <span class="stat-value">{$shop_stats.stock_syncs}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xs-4">
                                <div class="panel widget">
                                    <div class="widget-heading">{l s='Price Syncs' mod='stockpricesync'}</div>
                                    <div class="widget-body">
                                        <span class="stat-value">{$shop_stats.price_syncs}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row text-center">
                            <div class="col-xs-6">
                                <div class="panel widget">
                                    <div class="widget-heading">{l s='Success Rate' mod='stockpricesync'}</div>
                                    <div class="widget-body">
                                        <div class="progress">
                                            <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="{$shop_stats.success_rate}" aria-valuemin="0" aria-valuemax="100" style="width: {$shop_stats.success_rate}%;">
                                                {$shop_stats.success_rate}%
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xs-6">
                                <div class="panel widget">
                                    <div class="widget-heading">{l s='Last Sync' mod='stockpricesync'}</div>
                                    <div class="widget-body">
                                        {if isset($shop_stats.last_sync) && $shop_stats.last_sync}
                                            <span class="stat-value">{$shop_stats.last_sync|date_format:"%Y-%m-%d %H:%M:%S"}</span>
                                        {else}
                                            <span class="text-muted">{l s='Never' mod='stockpricesync'}</span>
                                        {/if}
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="panel-footer text-center">
                            <a href="{$current_link}&view_logs=1&shop_id={$view_shop->id_shop_remote}" class="btn btn-default">
                                <i class="icon-list-alt"></i> {l s='View Sync Logs' mod='stockpricesync'}
                            </a>
                        </div>
                    {else}
                        <div class="alert alert-warning">
                            <p>{l s='No synchronization statistics available for this shop yet.' mod='stockpricesync'}</p>
                        </div>
                    {/if}
                </div>
            </div>
        </div>
        
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-wrench"></i> {l s='Shop Actions' mod='stockpricesync'}
            </div>
            
            <div class="row text-center shop-actions">
                <div class="col-xs-4">
                    <a href="{$current_link}&edit_shop=1&id_shop_remote={$view_shop->id_shop_remote}" class="btn btn-default btn-block">
                        <i class="icon-pencil"></i> {l s='Edit Shop' mod='stockpricesync'}
                    </a>
                </div>
                <div class="col-xs-4">
                    <form method="post" action="{$current_link}">
                        <input type="hidden" name="sync_shop" value="1">
                        <input type="hidden" name="id_shop_remote" value="{$view_shop->id_shop_remote}">
                        <button type="submit" name="submit" class="btn btn-primary btn-block" onclick="return confirm('{l s='Are you sure you want to sync ALL stock and prices to this store?' mod='stockpricesync' js=1}');">
                            <i class="icon-refresh"></i> {l s='Sync Now' mod='stockpricesync'}
                        </button>
                        <select name="sync_type" class="form-control margin-top-10">
                            <option value="both">{l s='Stock & Prices' mod='stockpricesync'}</option>
                            <option value="stock">{l s='Stock Only' mod='stockpricesync'}</option>
                            <option value="price">{l s='Prices Only' mod='stockpricesync'}</option>
                        </select>
                    </form>
                </div>
                <div class="col-xs-4">
                    <a href="{$current_link}&generate_api_key=1&id_shop_remote={$view_shop->id_shop_remote}" class="btn btn-warning btn-block" onclick="return confirm('{l s='Are you sure you want to generate a new API key? This will invalidate the current one.' mod='stockpricesync' js=1}');">
                        <i class="icon-key"></i> {l s='Generate New API Key' mod='stockpricesync'}
                    </a>
                    <a href="{$current_link}&delete_shop=1&id_shop_remote={$view_shop->id_shop_remote}" class="btn btn-danger margin-top-10" onclick="return confirm('{l s='Are you sure you want to delete this store? This action cannot be undone.' mod='stockpricesync' js=1}');">
                        <i class="icon-trash"></i> {l s='Delete Shop' mod='stockpricesync'}
                    </a>
                </div>
            </div>
        </div>
        
        {* Connection Test Panel *}
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-exchange"></i> {l s='Connection Test' mod='stockpricesync'}
            </div>
            
            <div class="alert alert-info">
                <p><i class="icon-info-circle"></i> {l s='Test the connection to this remote shop to verify that API communications are working properly.' mod='stockpricesync'}</p>
            </div>
            
            <div class="text-center">
                <button id="test_connection" class="btn btn-info">
                    <i class="icon-refresh"></i> {l s='Test Connection' mod='stockpricesync'}
                </button>
                <div id="connection_result" class="margin-top-10"></div>
            </div>
            
            <div class="connection-details margin-top-20" style="display:none;">
                <h4>{l s='Connection Details' mod='stockpricesync'}</h4>
                <div class="table-responsive">
                    <table class="table">
                        <tbody>
                            <tr>
                                <th>{l s='Request URL:' mod='stockpricesync'}</th>
                                <td id="connection_url"></td>
                            </tr>
                            <tr>
                                <th>{l s='Response Status:' mod='stockpricesync'}</th>
                                <td id="connection_status"></td>
                            </tr>
                            <tr>
                                <th>{l s='Response Time:' mod='stockpricesync'}</th>
                                <td id="connection_time"></td>
                            </tr>
                            <tr>
                                <th>{l s='Response Body:' mod='stockpricesync'}</th>
                                <td><pre id="connection_body"></pre></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    {/if}
</div>

<script type="text/javascript">
    $(document).ready(function() {
        // Copy API key to clipboard
        $("#copy_api_key").click(function() {
            var copyText = document.getElementById("api_key");
            copyText.select();
            document.execCommand("copy");
            
            $(this).html('<i class="icon-check"></i> {l s="Copied!" mod='stockpricesync' js=1}');
            
            setTimeout(function() {
                $("#copy_api_key").html('<i class="icon-copy"></i> {l s="Copy" mod='stockpricesync' js=1}');
            }, 2000);
        });
        
        // Price adjustment preview
        $('input[name="price_percentage"]').on('input', function() {
            var percentage = parseFloat($(this).val()) || 0;
            var base_price = 100;
            var adjusted_price = base_price * (1 + (percentage / 100));
            $('#price_preview_value').text(adjusted_price.toFixed(2));
        });
        
// Test connection to remote shop
$("#test_connection").click(function() {
    var btn = $(this);
    var result = $('#connection_result');
    var connection_details = $('.connection-details');
    
    btn.attr('disabled', 'disabled');
    btn.html('<i class="icon-refresh icon-spin"></i> {l s="Testing..." mod='stockpricesync' js=1}');
    result.html('');
    connection_details.hide();
    
    var shopUrl = "{$view_shop->url|escape:'javascript':'UTF-8'}";
    var apiKey = "{$view_shop->api_key|escape:'javascript':'UTF-8'}";
    
    var startTime = new Date().getTime();
    
    // Asegurarse de que la URL no termine con slash para evitar dobles barras
    var cleanShopUrl = shopUrl.replace(/\/$/, '');
    
    // AJAX call to test connection
    $.ajax({
        url: cleanShopUrl + '/modules/stockpricesync/api/test.php',
        type: 'POST',
        dataType: 'json',
        contentType: 'application/json',
        data: JSON.stringify({
            shop_name: "{$shop_name|escape:'javascript':'UTF-8'}",
            test: true
        }),
        headers: {
            'X-API-Key': apiKey
        },
        success: function(response) {
            var endTime = new Date().getTime();
            var responseTime = endTime - startTime;
            
            if (response.success) {
                result.html('<div class="alert alert-success"><i class="icon-check"></i> ' + response.message + '</div>');
            } else {
                result.html('<div class="alert alert-danger"><i class="icon-remove"></i> ' + response.message + '</div>');
            }
            
            // Show connection details
            $('#connection_url').text(cleanShopUrl + '/modules/stockpricesync/api/test.php');
            $('#connection_status').html('<span class="badge badge-' + (response.success ? 'success' : 'danger') + '">' + (response.success ? 'Success' : 'Error') + '</span>');
            $('#connection_time').text(responseTime + 'ms');
            $('#connection_body').text(JSON.stringify(response, null, 2));
            connection_details.show();
        },
        error: function(xhr, status, error) {
            var endTime = new Date().getTime();
            var responseTime = endTime - startTime;
            
            result.html('<div class="alert alert-danger"><i class="icon-remove"></i> {l s="Connection error" mod='stockpricesync' js=1}: ' + status + ' ' + error + '</div>');
            
            // Show connection details
            $('#connection_url').text(cleanShopUrl + '/modules/stockpricesync/api/test.php');
            $('#connection_status').html('<span class="badge badge-danger">Error (' + xhr.status + ')</span>');
            $('#connection_time').text(responseTime + 'ms');
            $('#connection_body').text(xhr.responseText || 'No response received');
            connection_details.show();
        },
        complete: function() {
            btn.removeAttr('disabled');
            btn.html('<i class="icon-refresh"></i> {l s="Test Connection" mod='stockpricesync' js=1}');
        }
    });
});
    });
</script>

<style type="text/css">
    .margin-top-10 {
        margin-top: 10px;
    }
    .margin-top-20 {
        margin-top: 20px;
    }
    .shop-actions .btn {
        margin-bottom: 10px;
    }
    .widget {
        margin-bottom: 15px;
    }
    .widget .widget-heading {
        font-weight: bold;
        margin-bottom: 5px;
        color: #555;
    }
    .widget .widget-body {
        font-size: 24px;
        color: #00aff0;
    }
    .stat-value {
        font-size: 26px;
        font-weight: bold;
        color: #00aff0;
    }
    .price-preview {
        margin-top: 10px;
    }
</style>