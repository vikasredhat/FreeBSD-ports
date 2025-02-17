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


function audit_resource2str($resource_type = null) {
	$resources = [
		AUDIT_RESOURCE_USER => _('User'),
		AUDIT_RESOURCE_ZABBIX_CONFIG => _('Configuration of Zabbix'),
		AUDIT_RESOURCE_MEDIA_TYPE => _('Media type'),
		AUDIT_RESOURCE_HOST => _('Host'),
		AUDIT_RESOURCE_HOST_PROTOTYPE => _('Host prototype'),
		AUDIT_RESOURCE_ACTION => _('Action'),
		AUDIT_RESOURCE_GRAPH => _('Graph'),
		AUDIT_RESOURCE_GRAPH_PROTOTYPE => _('Graph prototype'),
		AUDIT_RESOURCE_GRAPH_ELEMENT => _('Graph element'),
		AUDIT_RESOURCE_USER_GROUP => _('User group'),
		AUDIT_RESOURCE_APPLICATION => _('Application'),
		AUDIT_RESOURCE_TRIGGER => _('Trigger'),
		AUDIT_RESOURCE_TRIGGER_PROTOTYPE => _('Trigger prototype'),
		AUDIT_RESOURCE_HOST_GROUP => _('Host group'),
		AUDIT_RESOURCE_ITEM => _('Item'),
		AUDIT_RESOURCE_ITEM_PROTOTYPE => _('Item prototype'),
		AUDIT_RESOURCE_IMAGE => _('Image'),
		AUDIT_RESOURCE_VALUE_MAP => _('Value map'),
		AUDIT_RESOURCE_IT_SERVICE => _('Service'),
		AUDIT_RESOURCE_MAP => _('Map'),
		AUDIT_RESOURCE_SCREEN => _('Screen'),
		AUDIT_RESOURCE_SCENARIO => _('Web scenario'),
		AUDIT_RESOURCE_DISCOVERY_RULE => _('Discovery rule'),
		AUDIT_RESOURCE_SLIDESHOW => _('Slide show'),
		AUDIT_RESOURCE_PROXY => _('Proxy'),
		AUDIT_RESOURCE_REGEXP => _('Regular expression'),
		AUDIT_RESOURCE_MAINTENANCE => _('Maintenance'),
		AUDIT_RESOURCE_SCRIPT => _('Script'),
		AUDIT_RESOURCE_MACRO => _('Macro'),
		AUDIT_RESOURCE_TEMPLATE => _('Template'),
		AUDIT_RESOURCE_ICON_MAP => _('Icon mapping'),
		AUDIT_RESOURCE_CORRELATION => _('Event correlation'),
		AUDIT_RESOURCE_DASHBOARD => _('Dashboard')
	];

	if (is_null($resource_type)) {
		natsort($resources);
		return $resources;
	}

	return isset($resources[$resource_type]) ? $resources[$resource_type] : _('Unknown resource');
}

function add_audit($action, $resourcetype, $details) {
	if (mb_strlen($details) > 128) {
		$details = mb_substr($details, 0, 125).'...';
	}

	$values = [
		'userid' => CWebUser::$data['userid'],
		'clock' => time(),
		'ip' => substr(CWebUser::getIp(), 0, 39),
		'action' => $action,
		'resourcetype' => $resourcetype,
		'details' => $details
	];

	try {
		DB::insert('auditlog', [$values]);
		return true;
	}
	catch (DBException $e) {
		return false;
	}
}

function add_audit_ext($action, $resourcetype, $resourceid, $resourcename, $table_name, $values_old, $values_new) {
	$values_diff = [];

	if ($action == AUDIT_ACTION_UPDATE && !empty($values_new)) {
		foreach ($values_new as $id => $value_new) {
			// log only the values that have changed, skip arrays
			if (isset($values_old[$id])
					&& !is_array($values_old[$id])
					&& !is_array($value_new)
					&& strcmp($values_old[$id], $value_new) != 0) {
				array_push($values_diff, $id);
			}
		}
		if (count($values_diff) == 0) {
			return true;
		}
	}

	if (mb_strlen($resourcename) > 255) {
		$resourcename = mb_substr($resourcename, 0, 252).'...';
	}

	/*
	 * CWebUser is not initialized in CUser->login() method.
	 * $userid with value NULL throws DBEXECUTE_ERROR later, so no audit record will be created.
	 */
	$userid = ($action == AUDIT_ACTION_LOGIN)
		? $resourceid
		: (CWebUser::$data ? CWebUser::$data['userid'] : null);

	$values = [
		'userid' => $userid,
		'clock' => time(),
		'ip' => substr(CWebUser::getIp(), 0, 39),
		'action' => $action,
		'resourcetype' => $resourcetype,
		'resourceid' => $resourceid,
		'resourcename' => $resourcename
	];

	try {
		$auditId = DB::insert('auditlog', [$values]);
		$auditId = reset($auditId);

		if ($action == AUDIT_ACTION_UPDATE) {
			$values = [];
			foreach ($values_diff as $id) {
				$values[] = [
					'auditid' => $auditId,
					'table_name' => $table_name,
					'field_name' => $id,
					'oldvalue' => $values_old[$id],
					'newvalue' => $values_new[$id]
				];
			}
			DB::insert('auditlog_details', $values);
		}

		return true;
	}
	catch (DBException $e) {
		return false;
	}
}

function add_audit_details($action, $resourcetype, $resourceid, $resourcename, $details = null, $userId = null) {
	if ($userId === null) {
		$userId = CWebUser::$data['userid'];
	}

	if (mb_strlen($resourcename) > 255) {
		$resourcename = mb_substr($resourcename, 0, 252).'...';
	}

	$values = [
		'userid' => $userId,
		'clock' => time(),
		'ip' => substr(CWebUser::getIp(), 0, 39),
		'action' => $action,
		'resourcetype' => $resourcetype,
		'resourceid' => $resourceid,
		'resourcename' => $resourcename,
		'details' => $details
	];
	try {
		DB::insert('auditlog', [$values]);
		return true;
	}
	catch (DBException $e) {
		return false;
	}
}
