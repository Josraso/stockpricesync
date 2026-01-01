{*
* Stock & Price Synchronizer Setup Wizard
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-cogs"></i> {l s='Initial Setup - Stock & Price Synchronizer' mod='stockpricesync'}
    </div>
    
    <div class="alert alert-info">
        <p>{l s='Welcome to the setup wizard for Stock & Price Synchronizer. The first step is to define this store\'s role in the synchronization process.' mod='stockpricesync'}</p>
    </div>
    
    <form id="shop_type_form" class="form-horizontal" action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}" method="post">
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Store Type:' mod='stockpricesync'}</label>
            <div class="col-lg-9">
                <div class="radio">
                    <label>
                        <input type="radio" name="STOCKPRICESYNC_SHOP_TYPE" value="MAIN" required>
                        <strong>{l s='Main Store (Sender)' mod='stockpricesync'}</strong>
                    </label>
                    <p class="help-block">
                        {l s='This is the main store that will send stock and price updates to other stores.' mod='stockpricesync'}<br>
                        {l s='Select this option if you want to centralize and control stock/prices from this store.' mod='stockpricesync'}
                    </p>
                </div>
                <div class="radio">
                    <label>
                        <input type="radio" name="STOCKPRICESYNC_SHOP_TYPE" value="CHILD" required>
                        <strong>{l s='Child Store (Receiver)' mod='stockpricesync'}</strong>
                    </label>
                    <p class="help-block">
                        {l s='This store will receive stock and price updates from a main store.' mod='stockpricesync'}<br>
                        {l s='Select this option if you want this store to have its stock/prices controlled by another store.' mod='stockpricesync'}
                    </p>
                </div>
            </div>
        </div>
        
        <div class="panel-footer">
            <button type="submit" name="submit_shop_type" class="btn btn-default pull-right">
                <i class="process-icon-next"></i> {l s='Continue' mod='stockpricesync'}
            </button>
        </div>
    </form>
</div>