//{namespace name=backend/newsletter2go/view/tracking}
//{block name="backend/newsletter2go/view/tracking"}
Ext.define('Shopware.apps.Newsletter2go.view.Cart', {
    extend: 'Ext.form.FieldSet',
    alias: 'widget.cart-nl2go',
    collapsible: true,
    collapsed: false,
    hidden: false,
    width: '100%',
    margin: 5,
    border: true,
    autoScroll: false,
    defaults: {
        labelWidth: 160,
        anchor: '100%'
    },
    snippets: {
        title: '{s name=cart/title}Cart Tracking{/s}'
    },
    initComponent: function () {
        var me = this;
        Ext.Ajax.request({
            url: '{url controller="Newsletter2go" action="testConnection"}',
            method: 'POST',
            success: function(response) {
                me.hidden = false;
                me.doLayout();
            },
            failure: function (response) {
                me.hidden = true;
                me.doLayout();
            }
        });
        me.title = me.snippets.title;
        me.items = me.createForm();
        me.registerEvents();

        me.callParent(arguments);
    },
    registerEvents: function () {
        this.addEvents('cartTracking');
        this.addEvents('savePreferences');
    },
    createForm: function () {
        var me = this,
            labelText,
            buttonText,
            data = me.record;
        if (data.trackCarts === '1') {
            labelText = ' Enabled';
            buttonText = 'Disable Tracking';
        } else {
            labelText = ' Disabled';
            buttonText = 'Enable Tracking';
        }

        var $store = array();
        Ext.Ajax.request({
            url: '{url controller="Newsletter2go" action="fetchCartMailings"}',
            method: 'POST',
            success: function(response) {
                // TODO: fill the store
            },
            failure: function (response) {
                // TODO: do nothing
            }
        });

        var mailingCombobox = {
            xtype: 'combobox',
            emptyText: 'Select Mailing',
            fieldLabel: 'Transactional Mailing ',
            store: ['a', 'b', 'c']
        };
        var hoursCombobox = {
            xtype: 'combobox',
            emptyText: 'Hours',
            fieldLabel: 'Send Mailing after X Hours ',
            store: ['1', '2', '3']
        };

        return [
            {
                xtype: 'box',
                style: 'margin-bottom: 5px',
                itemId: 'nl2goCartTrackingLabel',
                html: '<p>Shopping Cart Tracking:<span style="color: #0b6dbe">' + labelText  + '</span></p>'
            },
            {
                fieldLabel: '{s name=carttracking}label{/s}',
                name: 'carttracking',
                xtype: 'button',
                itemId: 'nl2goCartTrackingButton',
                cls: 'primary small',
                text: buttonText,
                style: 'margin-bottom: 5px',
                handler: function () {
                    me.fireEvent('cartTracking', me.record);
                }
            },
            mailingCombobox,
            hoursCombobox,
            {
                fieldLabel: '{s name=savePreferences}label{/s}',
                name: 'savePreferences',
                xtype: 'button',
                itemId: 'nl2goSavePreferencesButton',
                cls: 'primary small',
                text: 'save',
                style: 'margin-bottom: 5px',
                handler: function () {
                    me.fireEvent(
                        'savePreferences',
                        me,
                        {transactionMailingId: mailingCombobox.getValue(), handleCartAfter: hoursCombobox.getValue()}
                    );
                }
            },
        ];
    }
});
//{/block}