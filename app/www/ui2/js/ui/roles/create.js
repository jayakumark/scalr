Scalr.regPage('Scalr.ui.roles.create', function (loadParams, moduleParams) {
	return Ext.create('Ext.panel.Panel', {
        title: 'Choose a role creation method',
        width: 380,
        layout: 'hbox',

        tools: [{
            type: 'close',
            handler: function () {
                Scalr.event.fireEvent('close');
            }
        }],

        defaults: {
            xtype: 'button',
            ui: 'simple',
            cls: 'x-btn-simple-large',
            margin: '20 0 20 20',
            iconAlign: 'above'
        },

        items: [{
            xtype: 'button',
            text: 'New role',
            href: '#/roles/edit',
            hrefTarget: '_self',
            iconCls: 'x-icon-behavior-large x-icon-behavior-large-mixed',
            tooltip: 'Create a new empty Role, and manually add Images, Orchestration, and Global Variables.'
        }, {
            xtype: 'button',
            text: "Role from\n" +
                "non-Scalr server",
            href: '#/roles/import',
            hrefTarget: '_self',
            iconCls: 'x-icon-behavior-large x-icon-behavior-large-wizard',
            tooltip: 'Snapshot an existing Server that is not currently managed by Scalr, and use the snapshot as an Image for your new Role.',
            listeners: {
                boxready: function() {
                    this.btnInnerEl.applyStyles('margin-top: 68px; white-space: pre;');
                }
            }
        }, {
            xtype: 'button',
            text: 'Role builder',
            href: '#/roles/builder',
            hrefTarget: '_self',
            iconCls: 'x-icon-behavior-large x-icon-behavior-large-rolebuilder',
            tooltip: 'Use the Role Builder wizard to bundle supported software into an Image for your new Role.'
        }]
    });
});
