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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/js/configuration.host.list.js.php';

$widget = (new CWidget())
	->setTitle(_('Hosts'))
	->setControls(new CList([
		(new CForm('get'))
			->cleanItems()
			->setAttribute('aria-label', _('Main filter'))
			->addItem((new CList())
				->addItem([
					new CLabel(_('Group'), 'groupid'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					$data['pageFilter']->getGroupsCB()
				])
			),
		(new CTag('nav', true, (new CList())
			->addItem(new CRedirectButton(_('Create host'), (new CUrl('hosts.php'))
				->setArgument('form', 'create')
				->getUrl()
			))
			->addItem(
				(new CButton('form', _('Import')))
					->onClick('redirect("conf.import.php?rules_preset=host")')
					->removeId()
			)
		))
			->setAttribute('aria-label', _('Content controls'))
	]));

// filter
$filter = new CFilter(new CUrl('hosts.php'));

$filter
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->addFilterTab(_('Filter'), [
		(new CFormList())
			->addRow(_('Name'),
				(new CTextBox('filter_host', $data['filter']['host']))
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					->setAttribute('autofocus', 'autofocus')
			)
			->addRow(
				(new CLabel(_('Templates'), 'filter_templates__ms')),
				(new CMultiSelect([
					'name' => 'filter_templates[]',
					'object_name' => 'templates',
					'data' => $data['filter']['templates'],
					'popup' => [
						'parameters' => [
							'srctbl' => 'templates',
							'srcfld1' => 'hostid',
							'srcfld2' => 'host',
							'dstfrm' => $filter->getName(),
							'dstfld1' => 'filter_templates_'
						]
					]
				]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			)
			->addRow(_('Monitored by'),
				(new CRadioButtonList('filter_monitored_by', (int) $data['filter']['monitored_by']))
					->addValue(_('Any'), ZBX_MONITORED_BY_ANY)
					->addValue(_('Server'), ZBX_MONITORED_BY_SERVER)
					->addValue(_('Proxy'), ZBX_MONITORED_BY_PROXY)
					->setModern(true)
			)
			->addRow(
				(new CLabel(_('Proxy'), 'filter_proxyids__ms')),
				(new CMultiSelect([
					'name' => 'filter_proxyids[]',
					'object_name' => 'proxies',
					'data' => $data['proxies_ms'],
					'disabled' => ($data['filter']['monitored_by'] != ZBX_MONITORED_BY_PROXY),
					'popup' => [
						'parameters' => [
							'srctbl' => 'proxies',
							'srcfld1' => 'proxyid',
							'srcfld2' => 'host',
							'dstfrm' => 'zbx_filter',
							'dstfld1' => 'filter_proxyids_'
						]
					]
				]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			),
		(new CFormList())
			->addRow(_('DNS'),
				(new CTextBox('filter_dns', $data['filter']['dns']))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			)
			->addRow(_('IP'),
				(new CTextBox('filter_ip', $data['filter']['ip']))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			)
			->addRow(_('Port'),
				(new CTextBox('filter_port', $data['filter']['port']))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			)
	]);

$widget->addItem($filter);

// table hosts
$form = (new CForm())->setName('hosts');

$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_hosts'))->onClick("checkAll('".$form->getName()."', 'all_hosts', 'hosts');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sortField'], $data['sortOrder'], 'hosts.php'),
		_('Applications'),
		_('Items'),
		_('Triggers'),
		_('Graphs'),
		_('Discovery'),
		_('Web'),
		_('Interface'),
		_('Templates'),
		make_sorting_header(_('Status'), 'status', $data['sortField'], $data['sortOrder'], 'hosts.php'),
		_('Availability'),
		_('Agent encryption'),
		_('Info')
	]);

$current_time = time();

foreach ($data['hosts'] as $host) {
	// Select an interface from the list with highest priority.
	$interface = null;
	foreach ([INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_JMX, INTERFACE_TYPE_IPMI] as $interface_type) {
		$host_interfaces = array_filter($host['interfaces'], function($host_interface) use($interface_type) {
			return $host_interface['type'] == $interface_type;
		});
		if ($host_interfaces) {
			$interface = reset($host_interfaces);
			break;
		}
	}

	$description = [];

	if ($host['proxy_hostid'] != 0) {
		$description[] = $data['proxies'][$host['proxy_hostid']]['host'];
		$description[] = NAME_DELIMITER;
	}
	if ($host['discoveryRule']) {
		$description[] = (new CLink(
			$host['discoveryRule']['name'], 'host_prototypes.php?parent_discoveryid='.$host['discoveryRule']['itemid']
		))
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_ORANGE);
		$description[] = NAME_DELIMITER;
	}

	$description[] = new CLink($host['name'],
		'hosts.php?form=update&hostid='.$host['hostid'].url_param('groupid')
	);

	$maintenance_icon = false;
	$hostInterface = ($interface['useip'] == INTERFACE_USE_IP) ? $interface['ip'] : $interface['dns'];
	$hostInterface .= empty($interface['port']) ? '' : NAME_DELIMITER.$interface['port'];

	if ($host['status'] == HOST_STATUS_MONITORED) {
		if ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
			if (array_key_exists($host['maintenanceid'], $data['maintenances'])) {
				$maintenance = $data['maintenances'][$host['maintenanceid']];
				$maintenance_icon = makeMaintenanceIcon($host['maintenance_type'], $maintenance['name'],
					$maintenance['description']
				);
			}
			else {
				$maintenance_icon = makeMaintenanceIcon($host['maintenance_type'], _('Inaccessible maintenance'), '');
			}
		}

		$statusCaption = _('Enabled');
		$statusClass = ZBX_STYLE_GREEN;
		$confirm_message = _('Disable host?');
		$statusUrl = 'hosts.php?hosts[]='.$host['hostid'].'&action=host.massdisable'.url_param('groupid');
	}
	else {
		$statusCaption = _('Disabled');
		$statusUrl = 'hosts.php?hosts[]='.$host['hostid'].'&action=host.massenable'.url_param('groupid');
		$confirm_message = _('Enable host?');
		$statusClass = ZBX_STYLE_RED;
	}

	$status = (new CLink($statusCaption, $statusUrl))
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass($statusClass)
		->addConfirmation($confirm_message)
		->addSID();

	if ($maintenance_icon) {
		$status = [$maintenance_icon, $status];
	}
	elseif (count($data['maintenances'])) {
		$status->addClass(ZBX_STYLE_ICON_NONE);
	}

	order_result($host['parentTemplates'], 'name');

	$hostTemplates = [];
	$i = 0;

	foreach ($host['parentTemplates'] as $template) {
		$i++;

		if ($i > $data['config']['max_in_table']) {
			$hostTemplates[] = [' ', HELLIP()];

			break;
		}

		if (array_key_exists($template['templateid'], $data['writable_templates'])) {
			$caption = [
				(new CLink($template['name'], 'templates.php?form=update&templateid='.$template['templateid']))
					->addClass(ZBX_STYLE_LINK_ALT)
					->addClass(ZBX_STYLE_GREY)
			];
		}
		else {
			$caption = [
				(new CSpan($template['name']))->addClass(ZBX_STYLE_GREY)
			];
		}

		$parent_templates = $data['templates'][$template['templateid']]['parentTemplates'];

		if ($parent_templates) {
			order_result($parent_templates, 'name');
			$caption[] = ' (';

			foreach ($parent_templates as $parent_template) {
				if (array_key_exists($parent_template['templateid'], $data['writable_templates'])) {
					$caption[] = (new CLink($parent_template['name'],
						'templates.php?form=update&templateid='.$parent_template['templateid']
					))
						->addClass(ZBX_STYLE_LINK_ALT)
						->addClass(ZBX_STYLE_GREY);
				}
				else {
					$caption[] = (new CSpan($parent_template['name']))->addClass(ZBX_STYLE_GREY);
				}
				$caption[] = ', ';
			}
			array_pop($caption);

			$caption[] = ')';
		}

		if ($hostTemplates) {
			$hostTemplates[] = ', ';
		}

		$hostTemplates[] = $caption;
	}

	$info_icons = [];
	if ($host['flags'] == ZBX_FLAG_DISCOVERY_CREATED && $host['hostDiscovery']['ts_delete'] != 0) {
		$info_icons[] = getHostLifetimeIndicator($current_time, $host['hostDiscovery']['ts_delete']);
	}

	if ($host['tls_connect'] == HOST_ENCRYPTION_NONE
			&& ($host['tls_accept'] & HOST_ENCRYPTION_NONE) == HOST_ENCRYPTION_NONE
			&& ($host['tls_accept'] & HOST_ENCRYPTION_PSK) != HOST_ENCRYPTION_PSK
			&& ($host['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) != HOST_ENCRYPTION_CERTIFICATE) {
		$encryption = (new CDiv((new CSpan(_('None')))->addClass(ZBX_STYLE_STATUS_GREEN)))
			->addClass(ZBX_STYLE_STATUS_CONTAINER);
	}
	else {
		// Incoming encryption.
		if ($host['tls_connect'] == HOST_ENCRYPTION_NONE) {
			$in_encryption = (new CSpan(_('None')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}
		elseif ($host['tls_connect'] == HOST_ENCRYPTION_PSK) {
			$in_encryption = (new CSpan(_('PSK')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}
		else {
			$in_encryption = (new CSpan(_('CERT')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}

		// Outgoing encryption.
		$out_encryption = [];
		if (($host['tls_accept'] & HOST_ENCRYPTION_NONE) == HOST_ENCRYPTION_NONE) {
			$out_encryption[] = (new CSpan(_('None')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}
		else {
			$out_encryption[] = (new CSpan(_('None')))->addClass(ZBX_STYLE_STATUS_GREY);
		}

		if (($host['tls_accept'] & HOST_ENCRYPTION_PSK) == HOST_ENCRYPTION_PSK) {
			$out_encryption[] = (new CSpan(_('PSK')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}
		else {
			$out_encryption[] = (new CSpan(_('PSK')))->addClass(ZBX_STYLE_STATUS_GREY);
		}

		if (($host['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) == HOST_ENCRYPTION_CERTIFICATE) {
			$out_encryption[] = (new CSpan(_('CERT')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}
		else {
			$out_encryption[] = (new CSpan(_('CERT')))->addClass(ZBX_STYLE_STATUS_GREY);
		}

		$encryption = (new CDiv([$in_encryption, ' ', $out_encryption]))->addClass(ZBX_STYLE_STATUS_CONTAINER);
	}

	$table->addRow([
		new CCheckBox('hosts['.$host['hostid'].']', $host['hostid']),
		(new CCol($description))->addClass(ZBX_STYLE_NOWRAP),
		[
			new CLink(_('Applications'), 'applications.php?groupid='.$data['groupId'].'&hostid='.$host['hostid']),
			CViewHelper::showNum($host['applications'])
		],
		[
			new CLink(_('Items'), 'items.php?filter_set=1&hostid='.$host['hostid']),
			CViewHelper::showNum($host['items'])
		],
		[
			new CLink(_('Triggers'), 'triggers.php?groupid='.$data['groupId'].'&hostid='.$host['hostid']),
			CViewHelper::showNum($host['triggers'])
		],
		[
			new CLink(_('Graphs'), 'graphs.php?groupid='.$data['groupId'].'&hostid='.$host['hostid']),
			CViewHelper::showNum($host['graphs'])
		],
		[
			new CLink(_('Discovery'), 'host_discovery.php?hostid='.$host['hostid']),
			CViewHelper::showNum($host['discoveries'])
		],
		[
			new CLink(_('Web'), 'httpconf.php?&hostid='.$host['hostid']),
			CViewHelper::showNum($host['httpTests'])
		],
		$hostInterface,
		$hostTemplates,
		$status,
		getHostAvailabilityTable($host),
		$encryption,
		makeInformationList($info_icons)
	]);
}

$form->addItem([
	$table,
	$data['paging'],
	new CActionButtonList('action', 'hosts',
		[
			'host.massenable' => ['name' => _('Enable'), 'confirm' => _('Enable selected hosts?')],
			'host.massdisable' => ['name' => _('Disable'), 'confirm' => _('Disable selected hosts?')],
			'host.export' => ['name' => _('Export'), 'redirect' =>
				(new CUrl('zabbix.php'))
					->setArgument('action', 'export.hosts.xml')
					->setArgument('backurl', (new CUrl('hosts.php'))
						->setArgument('groupid', $data['pageFilter']->groupid)
						->setArgument('page', getPageNumber())
						->getUrl())
					->getUrl()
			],
			'host.massupdateform' => ['name' => _('Mass update')],
			'host.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected hosts?')]
		]
	)
]);

$widget->addItem($form);

return $widget;
