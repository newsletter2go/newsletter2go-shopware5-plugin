//{block name="backend/newsletter2go/app"}
Ext.define('Shopware.apps.Newsletter2go', {
    name: 'Shopware.apps.Newsletter2go',
    extend: 'Enlight.app.SubApplication',
    bulkLoad: true,
    loadPath: '{url action=load}',
    controllers: ['Main'],
    models: [],
    stores: [],
    views: ['main.Main', 'main.Config', 'main.ApiSettings', 'main.Connect', 'main.Tracking'],
    launch: function () {
        var me = this,
            mainController = me.getController('Main');
        return mainController.mainWindow;
    }
});
//{/block}
