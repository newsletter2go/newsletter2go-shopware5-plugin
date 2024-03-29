//{namespace name=backend/newsletter2go/view/config}
//{block name="backend/newsletter2go/view/config"}
Ext.define('Shopware.apps.Newsletter2go.view.main.Config', {
    extend: 'Ext.container.Container',
    alias: 'widget.newsletter2go-config',
    layout: 'vbox',
    initComponent: function () {
        var me = this;

        Ext.applyIf(me, {
            items: me.getItems()
        });

        me.callParent(arguments);
    },
    getItems: function () {
        var me = this;

        return [
            {
                xtype: 'api-settings',
                record: me.record
            },
            {
                xtype: 'tracking-nl2go',
                record: me.record
            },
            {
                xtype: 'connect-nl2go',
                record: me.record
            }
        ];
    }

});
//{/block}
