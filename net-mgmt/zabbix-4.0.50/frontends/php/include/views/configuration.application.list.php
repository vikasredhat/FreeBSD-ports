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


$widget = (new CWidget())
	->setTitle(_('Applications'))
	->setControls(new CList([
		(new CForm('get'))
			->cleanItems()
			->setAttribute('aria-label', _('Main filter'))
			->addItem((new CList())
				->addItem([
					new CLabel(_('Group'), 'groupid'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					$this->data['pageFilter']->getGroupsCB()
				])
				->addItem([
					new CLabel(_('Host'), 'hostid'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					$this->data['pageFilter']->getHostsCB()
				])
			),
		(new CTag('nav', true, ($data['hostid'] == 0)
			? (new CButton('form', _('Create application (select host first)')))->setEnabled(false)
			: new CRedirectButton(_('Create application'), (new CUrl('applications.php'))
				->setArgument('form', 'create')
				->setArgument('groupid', $data['pageFilter']->groupid)
				->setArgument('hostid', $data['pageFilter']->hostid)
				->getUrl()
			)
		))->setAttribute('aria-label', _('Content controls'))
	]))
	->addItem(get_header_host_table('applications', $this->data['hostid']));

// create form
$form = (new CForm())->setName('application_form');

// create table
$applicationTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_applications'))
				->onClick("checkAll('".$form->getName()."', 'all_applications', 'applications');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		($this->data['hostid'] > 0) ? null : _('Host'),
		make_sorting_header(_('Application'), 'name', $this->data['sort'], $this->data['sortorder']),
		_('Items'),
		$data['showInfoColumn'] ? _('Info') : null
	]);

$current_time = time();

foreach ($data['applications'] as $application) {
	$info_icons = [];

	// inherited app, display the template list
	if ($application['templateids']) {
		$name = makeApplicationTemplatePrefix($application['applicationid'], $data['parent_templates']);
		$name[] = $application['name'];
	}
	elseif ($application['flags'] == ZBX_FLAG_DISCOVERY_CREATED && $application['discoveryRule']) {
		$name = [(new CLink($application['discoveryRule']['name'],
						'disc_prototypes.php?parent_discoveryid='.$application['discoveryRule']['itemid']))
					->addClass(ZBX_STYLE_LINK_ALT)
					->addClass(ZBX_STYLE_ORANGE)
		];
		$name[] = NAME_DELIMITER.$application['name'];

		if ($application['applicationDiscovery']['ts_delete'] != 0) {
			$info_icons[] = getApplicationLifetimeIndicator(
				$current_time, $application['applicationDiscovery']['ts_delete']
			);
		}
	}
	else {
		$name = new CLink($application['name'],
			'applications.php?form=update&applicationid='.$application['applicationid'].
				'&hostid='.$application['hostid']
		);
	}

	$checkBox = new CCheckBox('applications['.$application['applicationid'].']', $application['applicationid']);
	$checkBox->setEnabled(!$application['discoveryRule']);

	$applicationTable->addRow([
		$checkBox,
		($this->data['hostid'] > 0) ? null : $application['host']['name'],
		(new CCol($name))->addClass(ZBX_STYLE_NOWRAP),
		[
			new CLink(
				_('Items'),
				'items.php?'.
					'hostid='.$application['hostid'].
					'&filter_set=1'.
					'&filter_application='.urlencode($application['name'])
			),
			CViewHelper::showNum(count($application['items']))
		],
		$data['showInfoColumn'] ? makeInformationList($info_icons) : null
	]);
}

// append table to form
$form->addItem([
	$applicationTable,
	$this->data['paging'],
	new CActionButtonList('action', 'applications',
		[
			'application.massenable' => ['name' => _('Enable'), 'confirm' => _('Enable selected applications?')],
			'application.massdisable' => ['name' => _('Disable'), 'confirm' => _('Disable selected applications?')],
			'application.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected applications?')]
		],
		$this->data['hostid']
	)
]);

// append form to widget
$widget->addItem($form);

return $widget;
