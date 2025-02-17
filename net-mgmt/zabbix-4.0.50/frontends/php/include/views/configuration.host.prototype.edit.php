<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


$discoveryRule = $data['discovery_rule'];
$hostPrototype = $data['host_prototype'];
$parentHost = $data['parent_host'];

require_once dirname(__FILE__).'/js/configuration.host.edit.js.php';
require_once dirname(__FILE__).'/js/configuration.host.prototype.edit.js.php';

$widget = (new CWidget())
	->setTitle(_('Host prototypes'))
	->addItem(get_header_host_table('hosts', $discoveryRule['hostid'], $discoveryRule['itemid']));

$divTabs = new CTabView();
if (!hasRequest('form_refresh')) {
	$divTabs->setSelected(0);
}

$frmHost = (new CForm())
	->setName('hostPrototypeForm.')
	->setAttribute('aria-labelledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('form', getRequest('form', 1))
	->addVar('parent_discoveryid', $discoveryRule['itemid'])
	->addVar('tls_accept', $parentHost['tls_accept']);

if ($hostPrototype['hostid'] != 0) {
	$frmHost->addVar('hostid', $hostPrototype['hostid']);
}

$hostList = new CFormList('hostlist');

if ($data['templates']) {
	$hostList->addRow(_('Parent discovery rules'), $data['templates']);
}

$hostTB = (new CTextBox('host', $hostPrototype['host'], (bool) $hostPrototype['templateid']))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setAttribute('maxlength', 128)
	->setAriaRequired()
	->setAttribute('autofocus', 'autofocus');
$hostList->addRow((new CLabel(_('Host name'), 'host'))->setAsteriskMark(), $hostTB);

$name = ($hostPrototype['name'] != $hostPrototype['host']) ? $hostPrototype['name'] : '';
$visiblenameTB = (new CTextBox('name', $name, (bool) $hostPrototype['templateid']))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setAttribute('maxlength', 128);
$hostList->addRow(_('Visible name'), $visiblenameTB);

// display inherited parameters only for hosts prototypes on hosts
if ($parentHost['status'] != HOST_STATUS_TEMPLATE) {
	$existingInterfaceTypes = [];
	foreach ($parentHost['interfaces'] as $interface) {
		$existingInterfaceTypes[$interface['type']] = true;
	}

	zbx_add_post_js('hostInterfacesManager.add('.CJs::encodeJson(array_values($parentHost['interfaces'])).');');
	zbx_add_post_js('hostInterfacesManager.disable();');

	// Zabbix agent interfaces
	$ifTab = (new CTable())
		->setId('agentInterfaces')
		->setHeader([
			'',
			_('IP address'),
			_('DNS name'),
			_('Connect to'),
			_('Port'),
			(new CColHeader(_('Default')))->setColSpan(2)
		]);

	$row = (new CRow())->setId('agentInterfacesFooter');
	if (!array_key_exists(INTERFACE_TYPE_AGENT, $existingInterfaceTypes)) {
		$row->addItem(new CCol());
		$row->addItem((new CCol(_('No agent interfaces found.')))->setColSpan(6));
	}
	$ifTab->addRow($row);

	$hostList->addRow(_('Agent interfaces'),
		(new CDiv($ifTab))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('data-type', 'agent')
			->setWidth(ZBX_HOST_INTERFACE_WIDTH)
	);

	// SNMP interfaces
	$ifTab = (new CTable())->setId('SNMPInterfaces');

	$row = (new CRow())->setId('SNMPInterfacesFooter');
	if (!array_key_exists(INTERFACE_TYPE_SNMP, $existingInterfaceTypes)) {
		$row->addItem(new CCol());
		$row->addItem((new CCol(_('No SNMP interfaces found.')))->setColSpan(6));
	}
	$ifTab->addRow($row);

	$hostList->addRow(_('SNMP interfaces'),
		(new CDiv($ifTab))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('data-type', 'snmp')
			->setWidth(ZBX_HOST_INTERFACE_WIDTH)
	);

	// JMX interfaces
	$ifTab = (new CTable())->setId('JMXInterfaces');

	$row = (new CRow())->setId('JMXInterfacesFooter');
	if (!array_key_exists(INTERFACE_TYPE_JMX, $existingInterfaceTypes)) {
		$row->addItem(new CCol());
		$row->addItem((new CCol(_('No JMX interfaces found.')))->setColSpan(6));
	}
	$ifTab->addRow($row);

	$hostList->addRow(_('JMX interfaces'),
		(new CDiv($ifTab))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('data-type', 'jmx')
			->setWidth(ZBX_HOST_INTERFACE_WIDTH)
	);

	// IPMI interfaces
	$ifTab = (new CTable())->setId('IPMIInterfaces');

	$row = (new CRow())->setId('IPMIInterfacesFooter');
	if (!array_key_exists(INTERFACE_TYPE_IPMI, $existingInterfaceTypes)) {
		$row->addItem(new CCol());
		$row->addItem((new CCol(_('No IPMI interfaces found.')))->setColSpan(6));
	}
	$ifTab->addRow($row);

	$hostList->addRow(
		_('IPMI interfaces'),
		(new CDiv($ifTab))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('data-type', 'ipmi')
			->setWidth(ZBX_HOST_INTERFACE_WIDTH)
	);

	// proxy
	$proxyTb = (new CTextBox('proxy_hostid',
		$parentHost['proxy_hostid'] != 0 ? $this->data['proxy']['host'] : _('(no proxy)'), true
	))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);
	$hostList->addRow(_('Monitored by proxy'), $proxyTb);
}

$hostList->addRow(_('Create enabled'),
	(new CCheckBox('status', HOST_STATUS_MONITORED))
		->setChecked(HOST_STATUS_MONITORED == $hostPrototype['status'])
);

$divTabs->addTab('hostTab', _('Host'), $hostList);

// groups
$groupList = new CFormList();

// existing groups
$groups = [];
foreach ($data['groups'] as $group) {
	$groups[] = [
		'id' => $group['groupid'],
		'name' => $group['name'],
		'inaccessible' => (array_key_exists('inaccessible', $group) && $group['inaccessible'])
	];
}
$groupList->addRow(
	(new CLabel(_('Groups'), 'group_links__ms'))->setAsteriskMark(),
	(new CMultiSelect([
		'name' => 'group_links[]',
		'object_name' => 'hostGroup',
		'disabled' => (bool) $hostPrototype['templateid'],
		'data' => $groups,
		'popup' => [
			'parameters' => [
				'srctbl' => 'host_groups',
				'srcfld1' => 'groupid',
				'dstfrm' => $frmHost->getName(),
				'dstfld1' => 'group_links_',
				'editable' => true,
				'normal_only' => true
			]
		]
	]))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired()
);

// new group prototypes
$customGroupTable = (new CTable())->setId('tbl_group_prototypes');

// buttons
$buttonColumn = (new CCol(
	(new CButton('group_prototype_add', _('Add')))->addClass(ZBX_STYLE_BTN_LINK)
))->setAttribute('colspan', 5);

$buttonRow = (new CRow())
	->setId('row_new_group_prototype')
	->addItem($buttonColumn);

$customGroupTable->addRow($buttonRow);
$groupList->addRow(_('Group prototypes'), (new CDiv($customGroupTable))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR));

$divTabs->addTab('groupTab', _('Groups'), $groupList);

// templates
$tmplList = new CFormList();

if ($hostPrototype['templateid']) {
	$linkedTemplateTable = (new CTable())
		->setAttribute('style', 'width: 100%;')
		->setHeader([_('Name')]);

	foreach ($hostPrototype['templates'] as $template) {
		$tmplList->addVar('templates['.$template['templateid'].']', $template['templateid']);

		if (array_key_exists($template['templateid'], $hostPrototype['writable_templates'])) {
			$template_link = (new CLink($template['name'],
				'templates.php?form=update&templateid='.$template['templateid']
			))->setTarget('_blank');
		}
		else {
			$template_link = new CSpan($template['name']);
		}

		$linkedTemplateTable->addRow([$template_link]);
	}

	$tmplList->addRow(_('Linked templates'),
		(new CDiv($linkedTemplateTable))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);
}
else {
	$disableids = [];

	$linkedTemplateTable = (new CTable())
		->setAttribute('style', 'width: 100%;')
		->setHeader([_('Name'), _('Action')]);

	foreach ($hostPrototype['templates'] as $template) {
		$tmplList->addVar('templates['.$template['templateid'].']', $template['templateid']);

		if (array_key_exists($template['templateid'], $hostPrototype['writable_templates'])) {
			$template_link = (new CLink($template['name'],
				'templates.php?form=update&templateid='.$template['templateid']
			))->setTarget('_blank');
		}
		else {
			$template_link = new CSpan($template['name']);
		}

		$linkedTemplateTable->addRow([
			$template_link,
			(new CCol(
				(new CSimpleButton(_('Unlink')))
					->onClick('javascript: submitFormWithParam('.
						'"'.$frmHost->getName().'", "unlink['.$template['templateid'].']", "1"'.
					');')
					->addClass(ZBX_STYLE_BTN_LINK)
			))->addClass(ZBX_STYLE_NOWRAP)
		]);

		$disableids[] = $template['templateid'];
	}

	$tmplList->addRow(_('Linked templates'),
		(new CDiv($linkedTemplateTable))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);

	// create new linked template table
	$newTemplateTable = (new CTable())
		->addRow([
			(new CMultiSelect([
				'name' => 'add_templates[]',
				'object_name' => 'templates',
				'popup' => [
					'parameters' => [
						'srctbl' => 'templates',
						'srcfld1' => 'hostid',
						'srcfld2' => 'host',
						'dstfrm' => $frmHost->getName(),
						'dstfld1' => 'add_templates_',
						'disableids' => $disableids
					]
				]
			]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		])
		->addRow([
			(new CSimpleButton(_('Add')))
				->onClick('javascript: submitFormWithParam("'.$frmHost->getName().'", "add_template", "1");')
				->addClass(ZBX_STYLE_BTN_LINK)
		]);

	$tmplList->addRow((new CLabel(_('Link new templates'), 'add_templates__ms')),
		(new CDiv($newTemplateTable))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);
}

$divTabs->addTab('templateTab', _('Templates'), $tmplList);

// display inherited parameters only for hosts prototypes on hosts
if ($parentHost['status'] != HOST_STATUS_TEMPLATE) {
	// IPMI
	$ipmiList = new CFormList();

	$ipmiList->addRow(_('Authentication algorithm'),
		(new CTextBox('ipmi_authtype', ipmiAuthTypes($parentHost['ipmi_authtype']), true))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	);
	$ipmiList->addRow(_('Privilege level'),
		(new CTextBox('ipmi_privilege', ipmiPrivileges($parentHost['ipmi_privilege']), true))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	);
	$ipmiList->addRow(_('Username'),
		(new CTextBox('ipmi_username', $parentHost['ipmi_username'], true))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	);
	$ipmiList->addRow(_('Password'),
		(new CTextBox('ipmi_password', $parentHost['ipmi_password'], true))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	);

	$divTabs->addTab('ipmiTab', _('IPMI'), $ipmiList);

	// macros
	$macros = $parentHost['macros'];
	if ($data['show_inherited_macros']) {
		$macros = mergeInheritedMacros($macros,
			getInheritedMacros(zbx_objectValues($hostPrototype['templates'], 'templateid'))
		);
	}
	$macros = array_values(order_macros($macros, 'macro'));

	$macrosView = new CView('hostmacros', [
		'macros' => $macros,
		'show_inherited_macros' => $data['show_inherited_macros'],
		'is_template' => false,
		'readonly' => true
	]);
	$divTabs->addTab('macroTab', _('Macros'), $macrosView->render());
}

$inventoryFormList = (new CFormList('inventorylist'))
	->addRow(null,
		(new CRadioButtonList('inventory_mode', (int) $hostPrototype['inventory']['inventory_mode']))
			->addValue(_('Disabled'), HOST_INVENTORY_DISABLED)
			->addValue(_('Manual'), HOST_INVENTORY_MANUAL)
			->addValue(_('Automatic'), HOST_INVENTORY_AUTOMATIC)
			->setReadonly($hostPrototype['templateid'] != 0)
			->setModern(true)
	);

$divTabs->addTab('inventoryTab', _('Host inventory'), $inventoryFormList);

// Encryption form list.
$encryption_form_list = (new CFormList('encryption'))
	->addRow(_('Connections to host'),
		(new CRadioButtonList('tls_connect', (int) $parentHost['tls_connect']))
			->addValue(_('No encryption'), HOST_ENCRYPTION_NONE)
			->addValue(_('PSK'), HOST_ENCRYPTION_PSK)
			->addValue(_('Certificate'), HOST_ENCRYPTION_CERTIFICATE)
			->setModern(true)
			->setEnabled(false)
	)
	->addRow(_('Connections from host'),
		(new CList())
			->addClass(ZBX_STYLE_LIST_CHECK_RADIO)
			->addItem((new CCheckBox('tls_in_none'))
				->setLabel(_('No encryption'))
				->setAttribute('disabled', 'disabled')
			)
			->addItem((new CCheckBox('tls_in_psk'))
				->setLabel(_('PSK'))
				->setAttribute('disabled', 'disabled')
			)
			->addItem((new CCheckBox('tls_in_cert'))
				->setLabel(_('Certificate'))
				->setAttribute('disabled', 'disabled')
			)
	)
	->addRow(_('PSK identity'),
		(new CTextBox('tls_psk_identity', $parentHost['tls_psk_identity'], false, 128))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->setAttribute('disabled', 'disabled')
	)
	->addRow(_('PSK'),
		(new CTextBox('tls_psk', $parentHost['tls_psk'], false, 512))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->setAttribute('disabled', 'disabled')
	)
	->addRow(_('Issuer'),
		(new CTextBox('tls_issuer', $parentHost['tls_issuer'], false, 1024))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->setAttribute('disabled', 'disabled')
	)
	->addRow(_x('Subject', 'encryption certificate'),
		(new CTextBox('tls_subject', $parentHost['tls_subject'], false, 1024))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->setAttribute('disabled', 'disabled')
	);

$divTabs->addTab('encryptionTab', _('Encryption'), $encryption_form_list);

/*
 * footer
 */
if ($hostPrototype['hostid'] != 0) {
	$btnDelete = new CButtonDelete(
		_('Delete selected host prototype?'),
		url_param('form').url_param('hostid').url_param('parent_discoveryid')
	);
	$btnDelete->setEnabled($hostPrototype['templateid'] == 0);

	$divTabs->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new CSubmit('clone', _('Clone')),
			$btnDelete,
			new CButtonCancel(url_param('parent_discoveryid'))
		]
	));
}
else {
	$divTabs->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel(url_param('parent_discoveryid'))]
	));
}

$frmHost->addItem($divTabs);
$widget->addItem($frmHost);

return $widget;
