//{namespace name=backend/newsletter2go/view/tracking}
//{block name="backend/newsletter2go/view/tracking"}
Ext.define('Shopware.apps.Newsletter2go.view.Tracking', {
    extend: 'Ext.form.FieldSet',
    alias: 'widget.tracking-nl2go',
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
        title: '{s name=tracking/title}Conversion Tracking{/s}'
    },
    initComponent: function () {
        var me = this,
            data = me.record;
        me.disabled = !data['testConnection'];
        me.title = me.snippets.title;
        me.items = me.createForm();
        me.registerEvents();

        me.callParent(arguments);
    },
    registerEvents: function () {
        this.addEvents('tracking');
    },
    createForm: function () {
        var me = this,
            labelText,
            buttonText,
            data = me.record;
        if (data['trackOrders'] === '1') {
                labelText = ' Enabled';
                buttonText = 'Disable Tracking';
        } else {
                labelText = ' Disabled';
                buttonText = 'Enable Tracking';
        }

        return [
            {
                xtype: 'box',
                style: 'margin-bottom: 5px',
                itemId: 'nl2goConversionTrackingLabel',
                html: '<p>Conversion Tracking:<span style="color: #0b6dbe">' + labelText  + '</span></p>'
            },
            {
                fieldLabel: '{s name=tracking}label{/s}',
                name: 'tracking',
                xtype: 'button',
                itemId: 'nl2goConversionTrackingButton',
                cls: 'primary small',
                text: buttonText,
                style: 'margin-bottom: 5px',
                handler: function () {
                    me.fireEvent('tracking', me.record);
                }

            }
        ];
    }
});
//{/block}