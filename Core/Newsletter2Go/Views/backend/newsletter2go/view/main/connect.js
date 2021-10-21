//{namespace name=backend/newsletter2go/view/login_settings}
//{block name="backend/newsletter2go/view/login_settings"}
Ext.define('Shopware.apps.Newsletter2go.view.main.Connect', {
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
    initComponent: function () {
        var me = this;

        me.title = 'Connect to Newsletter2Go';
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
            console.log("me.record", data);
        if (!1) {
            labelText = ' Disconnected';
            buttonText = 'Click here to connect';
            labelColor = '#be2322';
            companyInfo = '';
        } else {
            labelText = ' Connected';
            buttonText = 'Click here to disconnect';
            labelColor = '#31be45';
            companyInfo = 111//' to ' + data['company_name'] + ', ' + data['company_bill_address'];
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


