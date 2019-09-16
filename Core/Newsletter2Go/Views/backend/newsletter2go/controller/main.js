//{block name="backend/nl2go\controller\main"}
Ext.define('Shopware.apps.Newsletter2go.controller.Main', {
    extend: 'Ext.app.Controller',
    mainWindow: null,
    init: function () {
        var me = this,
            testConnection = false,
            companyName = null,
            companyBillAddress = null,
            store = [];

        Ext.Ajax.request({
            url: '{url controller="Newsletter2go" action="getData"}',
            method: 'POST',
            success: function(response) {
                var result = Ext.decode(response.responseText);
                result.data['testConnection'] = testConnection;
                result.data['company_name'] = companyName;
                result.data['company_bill_address'] = companyBillAddress;
                result.data['store'] = store;

                me.mainWindow = me.getView('Main').create({
                    record: result.data
                }).show();

                Ext.Ajax.request({
                    url: '{url controller="Newsletter2go" action="testConnection"}',
                    method: 'POST',
                    success: function(response) {
                        var result = Ext.decode(response.responseText);
                        console.log("testConnection", result);
                        if (result.success) {
                            me.mainWindow.record.testConnection = true;
                            me.mainWindow.record.company_name = result.data['company_name'];
                            me.mainWindow.record.company_bill_address = result.data['company_bill_address'];

                            Ext.Ajax.request({
                                url: '{url controller="Newsletter2go" action="fetchCartMailings"}',
                                method: 'POST',
                                success: function (response) {
                                    var result = Ext.decode(response.responseText);
                                    if (result.success && result.data != null) {
                                        result.data['mailings'].forEach(function (element) {
                                            store.push([element.id, element.name]);
                                        });
                                        me.mainWindow.record.newsletter_id = result.data['userIntegration']['newsletter_id'];
                                        me.mainWindow.record.handle_cart_as_abandoned_after = result.data['userIntegration']['handle_cart_as_abandoned_after'];
                                        me.mainWindow.record.store = store;
                                        // update the widgets
                                        let cartWidget = Ext.ComponentQuery.query('cart-nl2go')[0];
                                        let trackingWidget = Ext.ComponentQuery.query('tracking-nl2go')[0];
                                        cartWidget.updateContents();
                                        trackingWidget.updateContents();
                                    }
                                }
                            });
                        }
                        // enable and update the connect widget after testing connection
                        let connectWidget = Ext.ComponentQuery.query('connect-nl2go')[0];
                        connectWidget.setDisabled(false);
                        connectWidget.updateContents();

                    }
                });

            }
        });

        me.control({
            'api-settings': {
                resetApiKey: me.onApiKeyReset
            },
            'connect-nl2go': {
                connect: me.onConnect,
                disconnect: me.onDisconnect
            },
            'tracking-nl2go': {
                tracking: me.onTracking
            },
            'cart-nl2go': {
                cartTracking: me.onCartTracking,
                savePreferences: me.onSavePreferences
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
            'version=4126',
            'username=' + Ext.ComponentQuery.query('[name=shopUsername]')[0].value,
            'password=' + Ext.ComponentQuery.query('[name=shopApiKey]')[0].value,
            'language=' + Ext.editorLang.split('_')[0],
            'url=' + encodeURI(record.baseUrl),
            'callback=' + encodeURI(record.baseUrl) + 'Newsletter2goCallback'
        ];

        window.open(n2gUrl + '?' + params.join('&'), '_blank');

        let mainWindow = Ext.ComponentQuery.query('newsletter2go-main-window')[0];
        mainWindow.close();
    },
    onDisconnect: function (record) {
        Ext.Ajax.request({
            url: '{url controller="Newsletter2go" action="deleteConnectedAccount"}',
            method: 'POST',
            success: function(response) {
                let message = Ext.String.format('Newsletter2Go account disconnected successfully!', '');
                Shopware.Notification.createGrowlMessage('Success!', message, 'new message');
                record.testConnection = false;

                let connectWidget = Ext.ComponentQuery.query('connect-nl2go')[0];
                let button = connectWidget.getComponent('nl2goConnectionButton');
                let conLabel = connectWidget.getComponent('nl2goConnectionStatusLabel');
                button.setText('Click here to connect');
                conLabel.update('<p>Status:<span style="color:#be2322"> Disconnected</span></p>');

                let trackingWidget = Ext.ComponentQuery.query('tracking-nl2go')[0];
                trackingWidget.setDisabled(true);
                let cartWidget = Ext.ComponentQuery.query('cart-nl2go')[0];
                cartWidget.setDisabled(true);
            },
            failure: function (response) {
                let result = Ext.decode(response.responseText);
                let message = Ext.String.format(result.message, '');
                Shopware.Notification.createGrowlMessage('Error!', message, 'new message');
            }
        });
    },
    onTracking: function () {
        var message;

        Ext.Ajax.request({
            url: '{url controller="Newsletter2go" action="setTracking"}',
            method: 'POST',
            success: function(response) {
                var result = Ext.decode(response.responseText),
                    button = Ext.ComponentQuery.query('#nl2goConversionTrackingButton'),
                    label = Ext.ComponentQuery.query('#nl2goConversionTrackingLabel'),
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
                let result = Ext.decode(response.responseText),
                    button = Ext.ComponentQuery.query('#nl2goCartTrackingButton'),
                    label = Ext.ComponentQuery.query('#nl2goCartTrackingLabel'),
                    i,
                    labelText = result.data.trackCarts ? ' Enabled' : ' Disabled',
                    buttonText = result.data.trackCarts ? 'Disable Tracking' : 'Enable Tracking';

                for (i = 0; i < button.length; i++) {
                    button[i].setText(buttonText);
                    label[i].getEl().dom.firstElementChild.firstElementChild.textContent = labelText;
                }

                message = Ext.String.format('Newsletter2Go shopping cart tracking activated successfully!', '');
                Shopware.Notification.createGrowlMessage('Success!', message, 'new message');
            },
            failure: function(response) {
                let result = Ext.decode(response.responseText);
                message = Ext.String.format(result.message, '');
                Shopware.Notification.createGrowlMessage('Error!', message, 'new message');
            }
        });
    },
    onSavePreferences: function (cartForm, values) {
        var message;

        Ext.Ajax.request( {
            url: '{url controller="Newsletter2go" action="setCartMailingPreferences"}',
            method: 'POST',
            params: {
                transactionMailingId: values.transactionMailingId,
                handleCartAfter: values.handleCartAfter
            },
            success: function(response) {
                message = Ext.String.format('abandoned shopping cart handling reconfigured successfully!', '');
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
