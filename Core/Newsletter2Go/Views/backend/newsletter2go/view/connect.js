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
        var me = this,
            labelText,
            buttonText,
            labelColor,
            companyInfo,
            data = me.record;
        if (true) { // TODO: test Connection
            labelText = ' Disconnected';
            buttonText = 'Click here to connect';
            labelColor = '#be2322';
            companyInfo = '';
        } else {
            labelText = ' Connected';
            buttonText = 'Click here to disconnect';
            labelColor = '#31be45';
            companyInfo = ' to someCompanyName, Kebapstraße 123';
        }

        return [
            {
                xtype: 'box',
                style: 'margin-bottom: 5px',
                itemId: 'nl2goConnectionStatusLabel',
                html: '<p>Status:<span style="color:' + labelColor + '">' + labelText  + companyInfo + '</span></p>'
            },
            {
                xtype: 'button',
                text: buttonText,
                style: 'margin-bottom: 5px',
                handler: function () {
                    me.fireEvent('connect', me.record);
                }
            }
        ];
    }
});
//{/block}


