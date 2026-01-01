{*
* Child Shop Dashboard for Stock & Price Synchronizer
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-dashboard"></i> {l s='Stock & Price Synchronizer - Child Store Dashboard' mod='stockpricesync'}
        <a href="#" id="reset_shop_type_btn" class="btn btn-xs btn-default pull-right">{l s='Change Store Type' mod='stockpricesync'}</a>
    </div>
    
    <div class="alert alert-info">
        <p><i class="icon-info-circle"></i> {l s='This store is configured as a CHILD store that receives stock and price updates from a main store.' mod='stockpricesync'}</p>
    </div>
    
    {* Connection configuration panel *}
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-cog"></i> {l s='Connection Settings' mod='stockpricesync'}
        </div>
        
        <form class="form-horizontal" action="{$current_link}" method="post">
            <div class="form-group">
                <label class="control-label col-lg-3">{l s='Store name:' mod='stockpricesync'}</label>
                <div class="col-lg-9">
                    <input type="text" name="STOCKPRICESYNC_SHOP_NAME" value="{$shop_name|escape:'html':'UTF-8'}" class="fixed-width-xl form-control" required />
                    <p class="help-block">
                        {l s='Identifying name of this store in the main store.' mod='stockpricesync'}
                    </p>
                </div>
            </div>
            
            <div class="form-group">
                <label class="control-label col-lg-3">{l s='Main store URL:' mod='stockpricesync'}</label>
                <div class="col-lg-9">
                    <input type="url" name="STOCKPRICESYNC_MAIN_SHOP_URL" value="{$main_shop_url|escape:'html':'UTF-8'}" class="fixed-width-xxl form-control" required placeholder="https://mainstore.com" />
                    <p class="help-block">
                        {l s='Complete URL of the main store (e.g., https://mainstore.com).' mod='stockpricesync'}
                    </p>
                </div>
            </div>
            
            <div class="form-group">
                <label class="control-label col-lg-3">{l s='API Key:' mod='stockpricesync'}</label>
                <div class="col-lg-9">
                    <div class="input-group fixed-width-xxl">
                        <input type="text" name="STOCKPRICESYNC_API_KEY" value="{$api_key|escape:'html':'UTF-8'}" class="form-control" required id="api_key" />
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-default" id="copy_api_key">
                                <i class="icon-copy"></i> {l s='Copy' mod='stockpricesync'}
                            </button>
                        </span>
                    </div>
                    <p class="help-block">
                        {l s='API key provided by the main store for authentication.' mod='stockpricesync'}
                    </p>
                </div>
            </div>
            
            <div class="form-group">
                <label class="control-label col-lg-3">{l s='Verify SSL:' mod='stockpricesync'}</label>
                <div class="col-lg-9">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="STOCKPRICESYNC_VERIFY_SSL" id="STOCKPRICESYNC_VERIFY_SSL_on" value="1" {if $verify_ssl}checked="checked"{/if}>
                        <label for="STOCKPRICESYNC_VERIFY_SSL_on">{l s='Yes' mod='stockpricesync'}</label>
						<input type="radio" name="STOCKPRICESYNC_VERIFY_SSL" id="STOCKPRICESYNC_VERIFY_SSL_off" value="0" {if !$verify_ssl}checked="checked"{/if}>
                        <label for="STOCKPRICESYNC_VERIFY_SSL_off">{l s='No' mod='stockpricesync'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                    <p class="help-block">
                        {l s='Verify SSL certificates in connections. Disable only in development or when using self-signed certificates.' mod='stockpricesync'}
                    </p>
                </div>
            </div>

            <div class="panel-footer">
                <button type="submit" name="submit_child_config" class="btn btn-default pull-right">
                    <i class="process-icon-save"></i> {l s='Save' mod='stockpricesync'}
                </button>
            </div>
        </form>
    </div>
    
    {* Connection status and actions *}
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-exchange"></i> {l s='Connection Status' mod='stockpricesync'}
        </div>
        
        <div class="table-responsive">
            <table class="table">
                <tr>
                    <th>{l s='Store name:' mod='stockpricesync'}</th>
                    <td>{$shop_name|escape:'html':'UTF-8'}</td>
                </tr>
                <tr>
                    <th>{l s='Main store URL:' mod='stockpricesync'}</th>
                    <td>
                        {if $main_shop_url}
                            <a href="{$main_shop_url|escape:'html':'UTF-8'}" target="_blank">{$main_shop_url|escape:'html':'UTF-8'}</a>
                        {else}
                            <span class="text-danger">{l s='Not configured' mod='stockpricesync'}</span>
                        {/if}
                    </td>
                </tr>
                <tr>
                    <th>{l s='API Key:' mod='stockpricesync'}</th>
                    <td>
                        {if $api_key}
                            <span class="text-success">{l s='Configured' mod='stockpricesync'}</span>
                        {else}
                            <span class="text-danger">{l s='Not configured' mod='stockpricesync'}</span>
                        {/if}
                    </td>
                </tr>
                <tr>
                    <th>{l s='Connection status:' mod='stockpricesync'}</th>
                    <td>
                        {if $main_shop_url && $api_key}
                            <button id="test_connection" class="btn btn-info btn-sm">
                                <i class="icon-refresh"></i> {l s='Test connection' mod='stockpricesync'}
                            </button>
                            <span id="connection_result"></span>
                        {else}
                            <span class="text-warning">{l s='Pending configuration' mod='stockpricesync'}</span>
                        {/if}
                    </td>
                </tr>
            </table>
        </div>
    </div>
    
    {* Manual sync request panel *}
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-download"></i> {l s='Request Updates' mod='stockpricesync'}
        </div>
        
        <div class="alert alert-info">
            <p><i class="icon-info-circle"></i> {l s='Use this button to manually request stock and price updates from the main store.' mod='stockpricesync'}</p>
        </div>
        
        <form action="{$current_link}" method="post" class="form-horizontal">
            <div class="form-group">
                <label class="control-label col-lg-3">{l s='Update type:' mod='stockpricesync'}</label>
                <div class="col-lg-4">
                    <select name="sync_type" class="form-control">
                        <option value="both">{l s='Stock & Prices' mod='stockpricesync'}</option>
                        <option value="stock">{l s='Stock Only' mod='stockpricesync'}</option>
                        <option value="price">{l s='Prices Only' mod='stockpricesync'}</option>
                    </select>
                </div>
                <div class="col-lg-5">
                    <button type="submit" name="request_stock_prices" class="btn btn-primary">
                        <i class="icon-refresh"></i> {l s='Request Updates from Main Store' mod='stockpricesync'}
                    </button>
                </div>
            </div>
        </form>
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
                            <th>{l s='Type' mod='stockpricesync'}</th>
                            <th>{l s='Product' mod='stockpricesync'}</th>
                            <th>{l s='Updates' mod='stockpricesync'}</th>
                            <th>{l s='Status' mod='stockpricesync'}</th>
                            <th>{l s='Message' mod='stockpricesync'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$recent_logs item=log}
                            <tr>
                                <td>{$log.date_add|date_format:"%Y-%m-%d %H:%M:%S"}</td>
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
                                    {if $log.sync_type == 'stock' || $log.sync_type == 'both'}
                                        {if $log.quantity_old !== null && $log.quantity_new !== null}
                                            {l s='Stock:' mod='stockpricesync'} {$log.quantity_old} ? {$log.quantity_new}<br>
                                        {/if}
                                    {/if}
                                    {if $log.sync_type == 'price' || $log.sync_type == 'both'}
                                        {if $log.price_old !== null && $log.price_new !== null}
                                            {l s='Price:' mod='stockpricesync'} {displayPrice price=$log.price_old} ? {displayPrice price=$log.price_new}
                                        {/if}
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
        
// Test connection
$("#test_connection").click(function() {
    var btn = $(this);
    var result = $('#connection_result');
    
    btn.attr('disabled', 'disabled');
    btn.html('<i class="icon-refresh icon-spin"></i> {l s="Testing..." mod='stockpricesync' js=1}');
    result.html('');
    
    // Main shop URL and API key
    var mainShopUrl = $('input[name="STOCKPRICESYNC_MAIN_SHOP_URL"]').val();
    var apiKey = $('input[name="STOCKPRICESYNC_API_KEY"]').val();
    var shopName = $('input[name="STOCKPRICESYNC_SHOP_NAME"]').val();
    
    // Check if all fields are filled
    if (!mainShopUrl || !apiKey || !shopName) {
        result.html('<span class="text-danger"><i class="icon-remove"></i> {l s="Please fill in all configuration fields" mod='stockpricesync' js=1}</span>');
        btn.removeAttr('disabled');
        btn.html('<i class="icon-refresh"></i> {l s="Test connection" mod='stockpricesync' js=1}');
        return;
    }
    
    // Asegurarse de que la URL no termine con slash para evitar dobles barras
    var cleanMainShopUrl = mainShopUrl.replace(/\/$/, '');
    
    // AJAX call to test connection
    $.ajax({
        url: cleanMainShopUrl + '/modules/stockpricesync/api/test.php',
        type: 'POST',
        dataType: 'json',
        contentType: 'application/json',
        data: JSON.stringify({
            shop_name: shopName,
            shop_url: window.location.origin,
            test: true
        }),
        headers: {
            'X-API-Key': apiKey
        },
        success: function(response) {
            if (response.success) {
                result.html('<span class="text-success"><i class="icon-check"></i> ' + response.message + '</span>');
            } else {
                result.html('<span class="text-danger"><i class="icon-remove"></i> ' + response.message + '</span>');
            }
        },
        error: function(xhr, status, error) {
            result.html('<span class="text-danger"><i class="icon-remove"></i> {l s="Connection error" mod='stockpricesync' js=1}: ' + status + ' ' + error + '</span>');
        },
        complete: function() {
            btn.removeAttr('disabled');
            btn.html('<i class="icon-refresh"></i> {l s="Test connection" mod='stockpricesync' js=1}');
        }
    });
});
    });
</script>