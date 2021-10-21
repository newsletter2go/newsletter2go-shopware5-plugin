//{namespace name=backend/newsletter2go/view/main}
//{block name="backend/newsletter2go/view/main"}
Ext.define('Shopware.apps.Newsletter2go.view.main.Main', {
    extend: 'Enlight.app.Window',
    alias: 'widget.newsletter2go-main-window',
    layout: 'fit',
    width: '30%',
    height: '45%',
    maximizable: false,
    minimizable: true,
    stateful: true,
    resizable: true,
    stateId: 'Newsletter2goId',
    border: false,
    initComponent: function () {
        var me = this;
        Ext.applyIf(me, {
            title: 'Newsletter2Go',
            items: me.getItems(),
            bbar: me.getToolbar()
        });
        me.callParent(arguments);
    },
    getItems: function () {
        var me = this;

        return Ext.create('Ext.form.Panel', {
            collapsible: false,
            region: 'center',
            autoScroll: false,
            items: [
                {
                    xtype: 'newsletter2go-config',
                    record: me.record
                }
            ]
        });
    },
    getToolbar: function () {
        var me = this;
        return [
            '->',
            {
                text: 'Cancel',
                cls: 'primary',
                handler: function () {
                    me.destroy();
                }
            }
        ];
    }
});
//{/block}
