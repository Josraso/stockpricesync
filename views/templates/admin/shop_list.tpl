{*
* Shop List View for Stock & Price Synchronizer
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-shopping-cart"></i> {l s='Manage Remote Shops - Stock & Price Synchronizer' mod='stockpricesync'}
        <a href="{$current_link}" class="btn btn-default btn-xs pull-right">
            <i class="icon-arrow-left"></i> {l s='Back to Dashboard' mod='stockpricesync'}
        </a>
    </div>
    
    {* Add new shop panel *}
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-plus"></i> {l s='Add New Remote Shop' mod='stockpricesync'}
        </div>
        
        <form class="form-horizontal" action="{$current_link}" method="post">
            <div class="form-group">
                <label class="control-label col-lg-3 required">{l s='Shop name:' mod='stockpricesync'}</label>
                <div class="col-lg-6">
                    <input type="text" name="name" class="form-control" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="control-label col-lg-3 required">{l s='Shop URL:' mod='stockpricesync'}</label>
                <div class="col-lg-6">
                    <input type="url" name="url" class="form-control" required placeholder="https://example.com">
                </div>
            </div>
            
            <div class="form-group">
                <label class="control-label col-lg-3">{l s='API Key:' mod='stockpricesync'}</label>
                <div class="col-lg-6">
                    <div class="input-group">
                        <input type="text" name="api_key" class="form-control" id="api_key" value="{$auto_api_key}" readonly>
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-default" id="regenerate_api_key">
                                <i class="icon-refresh"></i> {l s='Regenerate' mod='stockpricesync'}
                            </button>
                            <button type="button" class="btn btn-default" id="copy_api_key">
                                <i class="icon-copy"></i> {l s='Copy' mod='stockpricesync'}
                            </button>
                        </span>
                    </div>
                    <p class="help-block">
                        {l s='This API key will be required in the child shop configuration.' mod='stockpricesync'}
                    </p>
                </div>
            </div>
            
            <div class="form-group">
                <label class="control-label col-lg-3">{l s='Status:' mod='stockpricesync'}</label>
                <div class="col-lg-6">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="active" id="active_on" value="1" checked="checked">
                        <label for="active_on">{l s='Active' mod='stockpricesync'}</label>
                        <input type="radio" name="active" id="active_off" value="0">
                        <label for="active_off">{l s='Inactive' mod='stockpricesync'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                </div>
            </div>
            
            <div class="form-group">
                <label class="control-label col-lg-3">{l s='Sync stock:' mod='stockpricesync'}</label>
                <div class="col-lg-6">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="sync_stock" id="sync_stock_on" value="1" checked="checked">
                        <label for="sync_stock_on">{l s='Yes' mod='stockpricesync'}</label>
                        <input type="radio" name="sync_stock" id="sync_stock_off" value="0">
                        <label for="sync_stock_off">{l s='No' mod='stockpricesync'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                </div>
            </div>
            
            <div class="form-group">
                <label class="control-label col-lg-3">{l s='Sync price:' mod='stockpricesync'}</label>
                <div class="col-lg-6">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="sync_price" id="sync_price_on" value="1" checked="checked">
                        <label for="sync_price_on">{l s='Yes' mod='stockpricesync'}</label>
                        <input type="radio" name="sync_price" id="sync_price_off" value="0">
                        <label for="sync_price_off">{l s='No' mod='stockpricesync'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                </div>
            </div>
            
            <div class="form-group">
                <label class="control-label col-lg-3">{l s='Price adjustment:' mod='stockpricesync'}</label>
                <div class="col-lg-2">
                    <div class="input-group">
                        <span class="input-group-addon">%</span>
                        <input type="number" name="price_percentage" step="0.01" value="0" class="form-control">
                    </div>
                </div>
                <div class="col-lg-7">
                    <p class="help-block">
                        {l s='Percentage to adjust prices when syncing to this shop. Use positive values to increase prices, negative to decrease.' mod='stockpricesync'}
                    </p>
                </div>
            </div>
            
            <div class="panel-footer">
                <button type="submit" name="submit_add_shop" class="btn btn-default pull-right">
                    <i class="process-icon-save"></i> {l s='Add Shop' mod='stockpricesync'}
                </button>
            </div>
        </form>
    </div>
    
    {* Connected shops table *}
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-list"></i> {l s='Connected Shops' mod='stockpricesync'}
            <span class="badge">{if isset($shops)}{count($shops)}{else}0{/if}</span>
        </div>
        
        {if isset($shops) && $shops}
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{l s='ID' mod='stockpricesync'}</th>
                            <th>{l s='Name' mod='stockpricesync'}</th>
                            <th>{l s='URL' mod='stockpricesync'}</th>
                            <th>{l s='Status' mod='stockpricesync'}</th>
                            <th>{l s='Stock Sync' mod='stockpricesync'}</th>
                            <th>{l s='Price Sync' mod='stockpricesync'}</th>
                            <th>{l s='Price Adj.' mod='stockpricesync'}</th>
                            <th>{l s='Last Updated' mod='stockpricesync'}</th>
                            <th>{l s='Actions' mod='stockpricesync'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$shops item=shop}
                            <tr>
                                <td>{$shop.id_shop_remote}</td>
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
                                <td>{$shop.date_upd|date_format:"%Y-%m-%d %H:%M:%S"}</td>
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
                <p>{l s='No stores connected yet. Use the form above to add your first child store.' mod='stockpricesync'}</p>
            </div>
        {/if}
    </div>
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
        
        // Regenerate API key
        $("#regenerate_api_key").click(function() {
            $.ajax({
                url: '{$current_link}&ajax=1&action=generateApiKey',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $("#api_key").val(response.api_key);
                    }
                }
            });
        });
    });
</script>