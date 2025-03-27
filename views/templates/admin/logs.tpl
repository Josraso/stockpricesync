{*
* Logs View for Stock & Price Synchronizer
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-list-alt"></i> {l s='Synchronization Logs - Stock & Price Synchronizer' mod='stockpricesync'}
        <a href="{$current_link}" class="btn btn-default btn-xs pull-right">
            <i class="icon-arrow-left"></i> {l s='Back to Dashboard' mod='stockpricesync'}
        </a>
    </div>
    
    {* Filters panel *}
    <div class="panel" id="filter-panel">
        <div class="panel-heading">
            <i class="icon-filter"></i> {l s='Filter Logs' mod='stockpricesync'}
            <a href="#" class="btn btn-default btn-xs pull-right" id="toggle-filters-btn">
                <i class="icon-angle-down"></i> {l s='Toggle Filters' mod='stockpricesync'}
            </a>
        </div>
        
        <div class="panel-body" id="filter-form-container" style="display:none;">
            <form method="post" class="form-horizontal" action="{$current_link}&view_logs=1">
                <div class="row">
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="control-label col-lg-4">{l s='Date range:' mod='stockpricesync'}</label>
                            <div class="col-lg-8">
                                <div class="input-group">
                                    <input type="text" name="date_from" class="datepicker form-control" value="{if isset($filter_date_from)}{$filter_date_from}{/if}" placeholder="{l s='From' mod='stockpricesync'}">
                                    <span class="input-group-addon">-</span>
                                    <input type="text" name="date_to" class="datepicker form-control" value="{if isset($filter_date_to)}{$filter_date_to}{/if}" placeholder="{l s='To' mod='stockpricesync'}">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="control-label col-lg-4">{l s='Sync type:' mod='stockpricesync'}</label>
                            <div class="col-lg-8">
                                <select name="sync_type" class="form-control">
                                    <option value="">{l s='All' mod='stockpricesync'}</option>
                                    <option value="stock" {if isset($filter_sync_type) && $filter_sync_type == 'stock'}selected{/if}>{l s='Stock Only' mod='stockpricesync'}</option>
                                    <option value="price" {if isset($filter_sync_type) && $filter_sync_type == 'price'}selected{/if}>{l s='Price Only' mod='stockpricesync'}</option>
                                    <option value="both" {if isset($filter_sync_type) && $filter_sync_type == 'both'}selected{/if}>{l s='Both' mod='stockpricesync'}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="control-label col-lg-4">{l s='Status:' mod='stockpricesync'}</label>
                            <div class="col-lg-8">
                                <select name="status" class="form-control">
                                    <option value="">{l s='All' mod='stockpricesync'}</option>
                                    <option value="1" {if isset($filter_status) && $filter_status == 1}selected{/if}>{l s='Success' mod='stockpricesync'}</option>
                                    <option value="0" {if isset($filter_status) && $filter_status == 0}selected{/if}>{l s='Error' mod='stockpricesync'}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="control-label col-lg-4">{l s='Shop:' mod='stockpricesync'}</label>
                            <div class="col-lg-8">
                                <select name="shop_id" class="form-control">
                                    <option value="">{l s='All Shops' mod='stockpricesync'}</option>
                                    {if isset($shops)}
                                        {foreach from=$shops item=shop}
                                            <option value="{$shop.id_shop_remote}" {if isset($filter_shop_id) && $filter_shop_id == $shop.id_shop_remote}selected{/if}>{$shop.name|escape:'html':'UTF-8'}</option>
                                        {/foreach}
                                    {/if}
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="control-label col-lg-4">{l s='Product Ref:' mod='stockpricesync'}</label>
                            <div class="col-lg-8">
                                <input type="text" name="product_reference" class="form-control" value="{if isset($filter_product_reference)}{$filter_product_reference}{/if}" placeholder="{l s='Product reference' mod='stockpricesync'}">
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="control-label col-lg-4">{l s='Log level:' mod='stockpricesync'}</label>
                            <div class="col-lg-8">
                                <select name="log_level" class="form-control">
                                    <option value="">{l s='All Levels' mod='stockpricesync'}</option>
                                    <option value="1" {if isset($filter_log_level) && $filter_log_level == 1}selected{/if}>{l s='Info' mod='stockpricesync'}</option>
                                    <option value="2" {if isset($filter_log_level) && $filter_log_level == 2}selected{/if}>{l s='Warning' mod='stockpricesync'}</option>
                                    <option value="3" {if isset($filter_log_level) && $filter_log_level == 3}selected{/if}>{l s='Error' mod='stockpricesync'}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-lg-8">
                        <div class="form-group">
                            <label class="control-label col-lg-2">{l s='Search:' mod='stockpricesync'}</label>
                            <div class="col-lg-10">
                                <input type="text" name="search" class="form-control" value="{if isset($filter_search)}{$filter_search}{/if}" placeholder="{l s='Search in references or messages' mod='stockpricesync'}">
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 text-right">
                        <button type="submit" name="filter_logs" class="btn btn-primary">
                            <i class="icon-search"></i> {l s='Filter' mod='stockpricesync'}
                        </button>
                        <button type="submit" name="reset_filters" class="btn btn-default">
                            <i class="icon-refresh"></i> {l s='Reset' mod='stockpricesync'}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    {* Export panel *}
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-download"></i> {l s='Export Logs' mod='stockpricesync'}
        </div>
        
        <div class="form-horizontal">
            <div class="row">
                <div class="col-lg-4">
                    <div class="form-group">
                        <label class="control-label col-lg-4">{l s='Format:' mod='stockpricesync'}</label>
                        <div class="col-lg-8">
                            <select id="export_format" class="form-control">
                                <option value="csv">CSV</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-8 text-right">
                    <form method="post" action="{$current_link}&view_logs=1" class="form-inline">
                        <input type="hidden" name="export_logs" value="1">
                        {* Include all active filters in hidden fields *}
                        {if isset($filter_date_from)}<input type="hidden" name="date_from" value="{$filter_date_from}">{/if}
                        {if isset($filter_date_to)}<input type="hidden" name="date_to" value="{$filter_date_to}">{/if}
                        {if isset($filter_sync_type)}<input type="hidden" name="sync_type" value="{$filter_sync_type}">{/if}
                        {if isset($filter_status)}<input type="hidden" name="status" value="{$filter_status}">{/if}
                        {if isset($filter_shop_id)}<input type="hidden" name="shop_id" value="{$filter_shop_id}">{/if}
                        {if isset($filter_product_reference)}<input type="hidden" name="product_reference" value="{$filter_product_reference}">{/if}
                        {if isset($filter_log_level)}<input type="hidden" name="log_level" value="{$filter_log_level}">{/if}
                        {if isset($filter_search)}<input type="hidden" name="search" value="{$filter_search}">{/if}
                        
                        <button type="submit" class="btn btn-default">
                            <i class="icon-download"></i> {l s='Export Filtered Logs' mod='stockpricesync'}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    {* Log Statistics *}
    <div class="row">
        <div class="col-lg-3">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-bar-chart"></i> {l s='Total Logs' mod='stockpricesync'}
                </div>
                <div class="panel-body text-center">
                    <h1>{if isset($stats.total)}{$stats.total}{else}0{/if}</h1>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-check"></i> {l s='Success Rate' mod='stockpricesync'}
                </div>
                <div class="panel-body text-center">
                    <h1>{if isset($stats.success_rate)}{$stats.success_rate}{else}0{/if}%</h1>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-cubes"></i> {l s='Stock Syncs' mod='stockpricesync'}
                </div>
                <div class="panel-body text-center">
                    <h1>{if isset($stats.stock_syncs)}{$stats.stock_syncs}{else}0{/if}</h1>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-money"></i> {l s='Price Syncs' mod='stockpricesync'}
                </div>
                <div class="panel-body text-center">
                    <h1>{if isset($stats.price_syncs)}{$stats.price_syncs}{else}0{/if}</h1>
                </div>
            </div>
        </div>
    </div>
    
    {* Log Table *}
    {if isset($logs) && $logs}
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>{l s='ID' mod='stockpricesync'}</th>
                        <th>{l s='Date' mod='stockpricesync'}</th>
                        <th>{l s='Shop' mod='stockpricesync'}</th>
                        <th>{l s='Type' mod='stockpricesync'}</th>
                        <th>{l s='Product Reference' mod='stockpricesync'}</th>
                        <th>{l s='Stock' mod='stockpricesync'}</th>
                        <th>{l s='Price' mod='stockpricesync'}</th>
                        <th>{l s='Status' mod='stockpricesync'}</th>
                        <th>{l s='Level' mod='stockpricesync'}</th>
                        <th>{l s='Message' mod='stockpricesync'}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$logs item=log}
                        <tr class="{if $log.status == 0}danger{elseif isset($log.log_level) && $log.log_level == 2}warning{elseif isset($log.log_level) && $log.log_level == 3}danger{/if}">
                            <td>{$log.id_log}</td>
                            <td>{$log.date_add|date_format:"%Y-%m-%d %H:%M:%S"}</td>
                            <td>
                                {if isset($log.shop_name)}
                                    {$log.shop_name|escape:'html':'UTF-8'}
                                {elseif isset($log.id_shop_remote) && $log.id_shop_remote}
                                    {l s='Shop ID:' mod='stockpricesync'} {$log.id_shop_remote}
                                {else}
                                    {l s='This shop' mod='stockpricesync'}
                                {/if}
                            </td>
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
                                        <div class="small text-muted">{$log.combination_reference|escape:'html':'UTF-8'}</div>
                                    {/if}
                                {else}
                                    -
                                {/if}
                            </td>
                            <td>
                                {if $log.sync_type == 'stock' || $log.sync_type == 'both'}
                                    {if $log.quantity_old !== null && $log.quantity_new !== null}
                                        <span class="badge">{$log.quantity_old}</span>
                                        <i class="icon-arrow-right"></i>
                                        <span class="badge">{$log.quantity_new}</span>
                                    {else}
                                        -
                                    {/if}
                                {/if}
                            </td>
                            <td>
                                {if $log.sync_type == 'price' || $log.sync_type == 'both'}
                                    {if $log.price_old !== null && $log.price_new !== null}
                                        <span class="badge">{displayPrice price=$log.price_old}</span>
                                        <i class="icon-arrow-right"></i>
                                        <span class="badge">{displayPrice price=$log.price_new}</span>
                                    {else}
                                        -
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
                            <td>
                                {if isset($log.log_level)}
                                    {if $log.log_level == 1}
                                        <span class="badge badge-info">{l s='Info' mod='stockpricesync'}</span>
                                    {elseif $log.log_level == 2}
                                        <span class="badge badge-warning">{l s='Warning' mod='stockpricesync'}</span>
                                    {elseif $log.log_level == 3}
                                        <span class="badge badge-danger">{l s='Error' mod='stockpricesync'}</span>
                                    {else}
                                        <span class="badge badge-default">{$log.log_level}</span>
                                    {/if}
                                {else}
                                    <span class="badge badge-default">{l s='Info' mod='stockpricesync'}</span>
                                {/if}
                            </td>
                            <td>
                                {if strlen($log.message) > 50}
                                    <span title="{$log.message|escape:'html':'UTF-8'}">{$log.message|truncate:50|escape:'html':'UTF-8'}</span>
                                    <a href="#" class="view-full-message" data-message="{$log.message|escape:'html':'UTF-8'}">{l s='View' mod='stockpricesync'}</a>
                                {else}
                                    {$log.message|escape:'html':'UTF-8'}
                                {/if}
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
        
        {* Pagination *}
        {if isset($pagination_total_pages) && $pagination_total_pages > 1}
            <div class="row">
                <div class="col-md-6">
                    <div class="pagination">
                        <ul class="pagination">
                            {if $pagination_page > 1}
                                <li>
                                    <a href="{$current_link}&view_logs=1&page=1{if isset($pagination_url_params)}{$pagination_url_params}{/if}" title="{l s='First page' mod='stockpricesync'}">
                                        <i class="icon-step-backward"></i>
                                    </a>
                                </li>
                                <li>
                                    <a href="{$current_link}&view_logs=1&page={$pagination_page-1}{if isset($pagination_url_params)}{$pagination_url_params}{/if}" title="{l s='Previous page' mod='stockpricesync'}">
                                        <i class="icon-chevron-left"></i>
                                    </a>
                                </li>
                            {else}
                                <li class="disabled">
                                    <span><i class="icon-step-backward"></i></span>
                                </li>
                                <li class="disabled">
                                    <span><i class="icon-chevron-left"></i></span>
                                </li>
                            {/if}
                            
                            {assign var=pagination_start value=max(1, $pagination_page-2)}
                            {assign var=pagination_end value=min($pagination_total_pages, $pagination_page+2)}
                            
                            {if $pagination_start > 1}
                                <li class="disabled">
                                    <span>...</span>
                                </li>
                            {/if}
                            
                            {for $p=$pagination_start to $pagination_end}
                                <li {if $p == $pagination_page}class="active"{/if}>
                                    <a href="{$current_link}&view_logs=1&page={$p}{if isset($pagination_url_params)}{$pagination_url_params}{/if}">{$p}</a>
                                </li>
                            {/for}
                            
                            {if $pagination_end < $pagination_total_pages}
                                <li class="disabled">
                                    <span>...</span>
                                </li>
                            {/if}
                            
                            {if $pagination_page < $pagination_total_pages}
                                <li>
                                    <a href="{$current_link}&view_logs=1&page={$pagination_page+1}{if isset($pagination_url_params)}{$pagination_url_params}{/if}" title="{l s='Next page' mod='stockpricesync'}">
                                        <i class="icon-chevron-right"></i>
                                    </a>
                                </li>
                                <li>
                                    <a href="{$current_link}&view_logs=1&page={$pagination_total_pages}{if isset($pagination_url_params)}{$pagination_url_params}{/if}" title="{l s='Last page' mod='stockpricesync'}">
                                        <i class="icon-step-forward"></i>
                                    </a>
                                </li>
                            {else}
                                <li class="disabled">
                                    <span><i class="icon-chevron-right"></i></span>
                                </li>
                                <li class="disabled">
                                    <span><i class="icon-step-forward"></i></span>
                                </li>
                            {/if}
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="pagination">
                        <div class="pull-right">
                            {l s='Display' mod='stockpricesync'}
                            <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                                {$pagination_items_per_page} <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a href="{$current_link}&view_logs=1&items_per_page=10{if isset($pagination_url_params)}{$pagination_url_params}{/if}">10</a></li>
                                <li><a href="{$current_link}&view_logs=1&items_per_page=20{if isset($pagination_url_params)}{$pagination_url_params}{/if}">20</a></li>
                                <li><a href="{$current_link}&view_logs=1&items_per_page=50{if isset($pagination_url_params)}{$pagination_url_params}{/if}">50</a></li>
                                <li><a href="{$current_link}&view_logs=1&items_per_page=100{if isset($pagination_url_params)}{$pagination_url_params}{/if}">100</a></li>
                            </ul>
                            {l s='items per page' mod='stockpricesync'}
                        </div>
                    </div>
                </div>
            </div>
        {/if}
    {else}
        <div class="alert alert-warning">
            <p>{l s='No logs found with the current filters.' mod='stockpricesync'}</p>
        </div>
    {/if}
    
    {* Modal for full message view *}
    <div class="modal fade" id="full-message-modal" tabindex="-1" role="dialog" aria-labelledby="messageModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="messageModalLabel">{l s='Log Message Details' mod='stockpricesync'}</h4>
                </div>
                <div class="modal-body">
                    <p id="full-message-content"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">{l s='Close' mod='stockpricesync'}</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Script para manejar el botón toggle
    var toggleBtn = document.getElementById('toggle-filters-btn');
    var filterContainer = document.getElementById('filter-form-container');
    
    if (toggleBtn && filterContainer) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Cambiar visibilidad
            if (filterContainer.style.display === 'none') {
                filterContainer.style.display = 'block';
                toggleBtn.querySelector('i').className = 'icon-angle-up';
            } else {
                filterContainer.style.display = 'none';
                toggleBtn.querySelector('i').className = 'icon-angle-down';
            }
        });
    }
    
    // Script para modal de mensajes
    var viewMessageLinks = document.querySelectorAll('.view-full-message');
    var messageContentElement = document.getElementById('full-message-content');
    
    if (viewMessageLinks.length > 0 && messageContentElement) {
        viewMessageLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                var message = this.getAttribute('data-message');
                messageContentElement.textContent = message;
                $('#full-message-modal').modal('show');
            });
        });
    }
    
    // Inicializar datepicker
    if ($.fn.datepicker) {
        $('.datepicker').datepicker({
            dateFormat: 'yy-mm-dd'
        });
    }
    
    // Mostrar filtros si alguno está activo
    {if isset($has_active_filters) && $has_active_filters}
        filterContainer.style.display = 'block';
        if (toggleBtn.querySelector('i')) {
            toggleBtn.querySelector('i').className = 'icon-angle-up';
        }
    {/if}
});
</script>