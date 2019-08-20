//{block name="backend/nl2go\controller\main"}
Ext.define('Shopware.apps.Newsletter2go.controller.Main', {
    extend: 'Ext.app.Controller',
    mainWindow: null,
    init: function () {
        var me = this;

        Ext.Ajax.request({
            url: '{url controller="Newsletter2go" action="getData"}',
            method: 'POST',
            success: function(response) {
                var result = Ext.decode(response.responseText);

                me.mainWindow = me.getView('Main').create({
                    record: result.data
                }).show();
            }
        });

        me.control({
            'api-settings': {
                resetApiKey: me.onApiKeyReset
            },
            'connect-nl2go': {
                connect: me.onConnect
            },
            'tracking-nl2go': {
                tracking: me.onTracking
            },
            'cart-nl2go': {
                cartTracking: me.onCartTracking
            }
        });

        me.callParent(arguments);
    },
    onApiKeyReset: function () {
        var message;

        Ext.Ajax.request({
            url: '{url controller="Newsletter2go" action="resetApiUser"}',
            method: 'POST',
            success: function(response) {
                var result = Ext.decode(response.responseText),
                    users = Ext.ComponentQuery.query('#nl2goShopUsername'),
                    keys = Ext.ComponentQuery.query('#nl2goShopApiKey'), 
                    i;

                for (i = 0; i < users.length; i++) {
                    users[i].setValue(result.data.apiUsername);
                    keys[i].setValue(result.data.apiKey);
                }

                message = Ext.String.format('Newsletter2Go integration for Shopware reconfigured successfully!', '');
                Shopware.Notification.createGrowlMessage('Success!', message, 'new message');
            },
            failure: function (response) {
                var result = Ext.decode(response.responseText);
                message = Ext.String.format(result.message, '');
                Shopware.Notification.createGrowlMessage('Error!', message, 'new message');
            }
        });
    },
    onConnect: function (record) {
        var n2gUrl = 'https://ui.newsletter2go.com/integrations/connect/SW/';
        var params = [
            'version=4116',
            'username=' + Ext.ComponentQuery.query('[name=shopUsername]')[0].value,
            'password=' + Ext.ComponentQuery.query('[name=shopApiKey]')[0].value,
            'language=' + Ext.editorLang.split('_')[0],
            'url=' + encodeURI(record.baseUrl),
            'callback=' + encodeURI(record.baseUrl) + 'Newsletter2goCallback'
        ];

        window.open(n2gUrl + '?' + params.join('&'), '_blank');
    },
    onTracking: function () {
        var message;

        Ext.Ajax.request({
            url: '{url controller="Newsletter2go" action="setTracking"}',
            method: 'POST',
            success: function(response) {
                var result = Ext.decode(response.responseText),
                    button = Ext.ComponentQuery.query('#nl2goTrackingButton'),
                    label = Ext.ComponentQuery.query('#nl2goTrackingLabel'),
                    i,
                    labelText = result.data.trackOrders ? ' Enabled' : ' Disabled',
                    buttonText = result.data.trackOrders ? 'Disable Tracking' : 'Enable Tracking';

                for (i = 0; i < button.length; i++) {
                    button[i].setText(buttonText);
                    label[i].getEl().dom.firstElementChild.firstElementChild.textContent = labelText;
                }

                message = Ext.String.format('Newsletter2Go conversion tracking reconfigured successfully!', '');
                Shopware.Notification.createGrowlMessage('Success!', message, 'new message');
            },
            failure: function (response) {
                var result = Ext.decode(response.responseText);
                message = Ext.String.format(result.message, '');
                Shopware.Notification.createGrowlMessage('Error!', message, 'new message');
            }
        });
    },
    onCartTracking: function () {
        var message;

        Ext.Ajax.request( {
            url: '{url controller="Newsletter2go" action="setCartTracking"}',
            method: 'POST',
            success: function(response) {
                var result = Ext.decode(response.responseText),
                    button = Ext.ComponentQuery.query('#nl2goCartTrackingButton'),
                    label = Ext.ComponentQuery.query('#nl2goCartTrackingLabel'),
                    i,
                    labelText = result.data.trackCarts ? ' Enabled' : ' Disabled',
                    buttonText = result.data.trackCarts ? 'Disable Tracking' : 'Enable Tracking';

                for (i = 0; i < button.length; i++) {
                    button[i].setText(buttonText);
                    label[i].getEl().dom.firstElementChild.firstElementChild.textContent = labelText;
                }

                message = Ext.String.format('Newsletter2Go shopping cart tracking reconfigured successfully!', '');
                Shopware.Notification.createGrowlMessage('Success!', message, 'new message');
            },
            failure: function(response) {
                var result = Ext.decode(response.responseText);
                message = Ext.String.format(result.message, '');
                Shopware.Notification.createGrowlMessage('Error!', message, 'new message');
            }
        });
    }
});
//{/block}
