//{namespace name=backend/newsletter2go/view/main}
//{block name="backend/newsletter2go/view/main"}
Ext.define('Shopware.apps.Newsletter2go.view.Main', {
    extend: 'Enlight.app.Window',
    alias: 'widget.newsletter2go-main-window',
    layout: 'fit',
    width: '30%',
    height: '67%',
    maximizable: false,
    minimizable: true,
    stateful: true,
    resizable: true,
    stateId: 'Newsletter2goId',
    border: false,
    snippets: {
        title: '{s name=config/title} Newsletter2Go {/s}',
        cancel: '{s name=config/cancel}Cancel{/s}'
    },
    initComponent: function () {
        var me = this;
        Ext.Ajax.request({
            url: '{url controller="Newsletter2go" action="testConnection"}',
            method: 'POST',
            success: function(response) {
                me.height = '67%';
                me.doLayout();
            },
            failure: function (response) {
                me.height = '52%';
                me.doLayout();
            }
        });
        Ext.applyIf(me, {
            title: me.snippets.title,
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
                text: me.snippets.cancel,
                cls: 'primary',
                handler: function () {
                    me.destroy();
                }
            }
        ];
    }
});
//{/block}
