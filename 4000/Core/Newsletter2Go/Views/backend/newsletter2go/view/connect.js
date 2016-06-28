//{namespace name=backend/newsletter2go/view/login_settings}
//{block name="backend/newsletter2go/view/login_settings"}
Ext.define('Shopware.apps.Newsletter2go.view.Connect', {
    extend: 'Ext.form.FieldSet',
    alias: 'widget.connect-nl2go',
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
        title: '{s name=general/title}Connect to Newsletter2Go{/s}'
    },
    initComponent: function () {
        var me = this;

        me.title = me.snippets.title;
        me.items = me.createForm();
        // me.registerEvents();

        me.callParent(arguments);
    },
    createForm: function () {
        var me = this;
        
        return [
            {
                xtype: 'button',
                text: 'Click here to connect',
                style: 'margin-bottom: 5px',
                handler: function () {
                    me.fireEvent('connect', me.record);
                }
            }
        ];
    }
});
//{/block}


