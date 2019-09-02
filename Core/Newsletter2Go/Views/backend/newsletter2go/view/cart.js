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

        var store = [];
        Ext.Ajax.request({
            url: '{url controller="Newsletter2go" action="fetchCartMailings"}',
            method: 'POST',
            success: function(response) {
                console.log("response", response);
                var result = Ext.decode(response.responseText);
                console.log("success", result.success);
                console.log("data", result.data);
                if (result.success && result.data != null) {
                    result.data.forEach(function(element) {
                        console.log(element);
                        store.push([element.id, element.name]);
                    });
                }
            }
        });

        var mailingCombobox = Ext.create('Ext.form.field.ComboBox', {
            name: 'mailing',
            queryMode: 'local',
            margin: '0 0 10',
            anchor: '100%',
            valueField: 'id',
            editable: false,
            labelWidth: 130,
            emptyText: 'transactional Mailing',
            fieldLabel: 'Transactional Mailing ',
            value: null,
            displayField: 'name',
            store: new Ext.data.SimpleStore({
                fields:['id', 'name'],
                data: store
            })
        });
        var hoursCombobox = {
            xtype: 'numberfield',
            anchor: '100%',
            name: 'bottles',
            fieldLabel: 'Send Mailing after X Hours ',
            value: 24,
            maxValue: 24,
            minValue: 1,
            handler: function() {
                this.up('form').down('[name=bottles]').spinDown();
            }
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
            hoursCombobox,
            mailingCombobox,
            {
                fieldLabel: '{s name=savePreferences}label{/s}',
                name: 'savePreferences',
                xtype: 'button',
                itemId: 'nl2goSavePreferencesButton',
                cls: 'primary small',
                text: 'save',
                style: 'margin-bottom: 5px',
                handler: function () {
                    var selectedValues = {
                        transactionMailingId: mailingCombobox.value,
                        handleCartAfter: hoursCombobox.value
                    };
                    me.fireEvent(
                        'savePreferences',
                        me.record,
                        selectedValues
                    );
                }
            },
        ];
    }
});
//{/block}