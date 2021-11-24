//{namespace name=backend/newsletter2go/view/api_settings}
//{block name="backend/newsletter2go/view/api_settings"}
Ext.define('Shopware.apps.Newsletter2go.view.main.ApiSettings', {
    extend: 'Ext.form.FieldSet',
    alias: 'widget.api-settings',
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
      title: '{s name="general/title"}Shopware API Access Details{/s}',
    },
    initComponent: function () {
        var me = this;

        me.title = this.snippets.title;
        me.items = me.createForm();
        me.registerEvents();

        me.callParent(arguments);
    },
    registerEvents: function () {
        this.addEvents('resetApiKey');
    },
    createForm: function () {
        var me = this,
            data = me.record;
        return [
            Ext.create('Ext.form.field.Text', {
                itemId: 'nl2goShopUsername',
                name: 'shopUsername',
                fieldLabel: 'Username',
                minWidth: 250,
                readOnly: true,
                value: data.apiUsername
            }),
            Ext.create('Ext.form.field.Text', {
                itemId: 'nl2goShopApiKey',
                name: 'shopApiKey',
                fieldLabel: 'API Key',
                minWidth: 250,
                readOnly: true,
                value: data.apiKey
            }),
            {
                xtype: 'button',
                text: 'Reset API Key',
                style: 'margin-bottom: 5px',
                handler: function () {
                    me.fireEvent('resetApiKey');
                }
            }
        ];
    }
});
//{/block}
