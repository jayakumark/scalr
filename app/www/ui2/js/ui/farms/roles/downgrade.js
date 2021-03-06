Scalr.regPage('Scalr.ui.farms.roles.downgrade', function (loadParams, moduleParams) {
	return Ext.create('Ext.form.Panel', {
		width: 700,
		title: 'Downgrade farm role to previous version',
		fieldDefaults: {
			anchor: '100%',
			labelWidth: 130
		},
		items: [{
			xtype: 'fieldset',
			title: 'Available roles',
			items: moduleParams['history']
		}],

		dockedItems: [{
			xtype: 'container',
			cls: 'x-docked-buttons',
			dock: 'bottom',
			layout: {
				type: 'hbox',
				pack: 'center'
			},
			items: [{
				xtype: 'button',
				text: 'Downgrade',
				handler: function() {
					Scalr.Request({
						processBox: {
							type: 'save'
						},
						url: '/farms/' + loadParams['farmId'] + '/roles/' + loadParams['farmRoleId'] + '/xDowngrade',
						form: this.up('form').getForm(),
						success: function () {
							//Scalr.event.fireEvent('close');
						}
					});
				}
			}, {
				xtype: 'button',
				text: 'Cancel',
				handler: function() {
					Scalr.event.fireEvent('close');
				}
			}]
		}]
	});
});
