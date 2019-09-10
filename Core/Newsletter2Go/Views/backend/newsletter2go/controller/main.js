//{block name="backend/nl2go\controller\main"}
Ext.define('Shopware.apps.Newsletter2go.controller.Main', {
    extend: 'Ext.app.Controller',
    mainWindow: null,
    init: function () {
        var me = this,
            testConnection = false,
            companyName = null,
            companyBillAddress = null,
            handleCartAsAbandonedAfter = null,
            newsletterId = null,
            store = [];

        Ext.Ajax.request({
            url: '{url controller="Newsletter2go" action="testConnection"}',
            method: 'POST',
            success: function(response) {
                var result = Ext.decode(response.responseText);
                if (result.success) {
                    testConnection = true;
                    companyName = result.data['company_name'];
                    companyBillAddress = result.data['company_bill_address'];

                    Ext.Ajax.request({
                        url: '{url controller="Newsletter2go" action="fetchCartMailings"}',
                        method: 'POST',
                        success: function(response) {
                            console.log(response);
                            var result = Ext.decode(response.responseText);
                            if (result.success && result.data != null) {
                                result.data['mailings'].forEach(function(element) {
                                    store.push([element.id, element.name]);
                                });
                                newsletterId = result.data['userIntegration']['newsletter_id'];
                                handleCartAsAbandonedAfter = result.data['userIntegration']['handle_cart_as_abandoned_after'];
                            }

                            Ext.Ajax.request({
                                url: '{url controller="Newsletter2go" action="getData"}',
                                method: 'POST',
                                success: function(response) {
                                    var result = Ext.decode(response.responseText);
                                    result.data['testConnection'] = testConnection;
                                    result.data['company_name'] = companyName;
                                    result.data['company_bill_address'] = companyBillAddress;
                                    result.data['handle_cart_as_abandoned_after'] = handleCartAsAbandonedAfter;
                                    result.data['newsletter_id'] = newsletterId;
                                    result.data['store'] = store;

                                    me.mainWindow = me.getView('Main').create({
                                        record: result.data
                                    }).show();
                                }
                            });
                        }
                    });
                } else {
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
                        }
                    });
                }
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
            'version=4116',
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
                var result = Ext.decode(response.responseText);

                message = Ext.String.format('Newsletter2Go account disconnected successfully!', '');
                Shopware.Notification.createGrowlMessage('Success!', message, 'new message');

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
                var result = Ext.decode(response.responseText);
                message = Ext.String.format(result.message, '');
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

                message = Ext.String.format('Newsletter2Go shopping cart tracking activated successfully!', '');
                Shopware.Notification.createGrowlMessage('Success!', message, 'new message');
            },
            failure: function(response) {
                var result = Ext.decode(response.responseText);
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
                var result = Ext.decode(response.responseText);

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
