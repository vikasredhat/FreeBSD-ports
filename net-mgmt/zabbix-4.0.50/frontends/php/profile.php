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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/users.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';
require_once dirname(__FILE__).'/include/media.inc.php';

$page['title'] = _('User profile');
$page['file'] = 'profile.php';
$page['scripts'] = ['class.cviewswitcher.js'];

ob_start();

if (CWebUser::isGuest() || !CWebUser::isLoggedIn()) {
	access_deny(ACCESS_DENY_PAGE);
}

require_once dirname(__FILE__).'/include/page_header.php';

$themes = array_keys(Z::getThemes());
$themes[] = THEME_DEFAULT;

//	VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	'password1' =>			[T_ZBX_STR, O_OPT, null, null, 'isset({update}) && isset({form}) && ({form} != "update") && isset({change_password})'],
	'password2' =>			[T_ZBX_STR, O_OPT, null, null, 'isset({update}) && isset({form}) && ({form} != "update") && isset({change_password})'],
	'lang' =>				[T_ZBX_STR, O_OPT, null, null, null],
	'theme' =>				[T_ZBX_STR, O_OPT, null, IN('"'.implode('","', $themes).'"'), 'isset({update})'],
	'autologin' =>			[T_ZBX_INT, O_OPT, null, IN('1'), null],
	'autologout' =>			[T_ZBX_STR, O_OPT, null, null, null, _('Auto-logout')],
	'autologout_visible' =>	[T_ZBX_STR, O_OPT, null, IN('1'), null],
	'url' =>				[T_ZBX_STR, O_OPT, null, null, 'isset({update})'],
	'refresh' =>			[T_ZBX_STR, O_OPT, null, null, 'isset({update})', _('Refresh')],
	'rows_per_page' => [T_ZBX_INT, O_OPT, null, BETWEEN(1, 999999), 'isset({update})', _('Rows per page')],
	'change_password' =>	[T_ZBX_STR, O_OPT, null, null, null],
	'user_medias' =>		[T_ZBX_STR, O_OPT, null, NOT_EMPTY, null],
	'user_medias_to_del' =>	[T_ZBX_STR, O_OPT, null, null, null],
	'new_media' =>			[T_ZBX_STR, O_OPT, null, null, null],
	'enable_media' =>		[T_ZBX_INT, O_OPT, null, null, null],
	'disable_media' =>		[T_ZBX_INT, O_OPT, null, null, null],
	'messages' =>			[T_ZBX_STR, O_OPT, null, null, null],
	// actions
	'update'=>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null],
	'cancel'=>				[T_ZBX_STR, O_OPT, P_SYS, null, null],
	'del_user_media'=>		[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null],
	// form
	'form'=>				[T_ZBX_STR, O_OPT, P_SYS, null, null],
	'form_refresh'=>		[T_ZBX_INT, O_OPT, null, null, null]
];
check_fields($fields);

$_REQUEST['autologin'] = getRequest('autologin', 0);

// secondary actions
if (isset($_REQUEST['new_media'])) {
	$_REQUEST['user_medias'] = getRequest('user_medias', []);
	array_push($_REQUEST['user_medias'], $_REQUEST['new_media']);
}
elseif (isset($_REQUEST['user_medias']) && isset($_REQUEST['enable_media'])) {
	if (isset($_REQUEST['user_medias'][$_REQUEST['enable_media']])) {
		$_REQUEST['user_medias'][$_REQUEST['enable_media']]['active'] = 0;
	}
}
elseif (isset($_REQUEST['user_medias']) && isset($_REQUEST['disable_media'])) {
	if (isset($_REQUEST['user_medias'][$_REQUEST['disable_media']])) {
		$_REQUEST['user_medias'][$_REQUEST['disable_media']]['active'] = 1;
	}
}
elseif (isset($_REQUEST['del_user_media'])) {
	$user_medias_to_del = getRequest('user_medias_to_del', []);
	foreach ($user_medias_to_del as $mediaid) {
		if (isset($_REQUEST['user_medias'][$mediaid])) {
			unset($_REQUEST['user_medias'][$mediaid]);
		}
	}
}
// primary actions
elseif (isset($_REQUEST['cancel'])) {
	ob_end_clean();
	redirect(ZBX_DEFAULT_URL);
}
elseif (hasRequest('update')) {
	$auth_type = getUserAuthenticationType(CWebUser::$data['userid']);

	if ($auth_type != ZBX_AUTH_INTERNAL) {
		$_REQUEST['password1'] = $_REQUEST['password2'] = null;
	}
	else {
		$_REQUEST['password1'] = getRequest('password1');
		$_REQUEST['password2'] = getRequest('password2');
	}

	if ($_REQUEST['password1'] != $_REQUEST['password2']) {
		show_error_message(_('Cannot update user. Both passwords must be equal.'));
	}
	elseif (isset($_REQUEST['password1']) && CWebUser::$data['alias'] == ZBX_GUEST_USER && !zbx_empty($_REQUEST['password1'])) {
		show_error_message(_('For guest, password must be empty'));
	}
	elseif (isset($_REQUEST['password1']) && CWebUser::$data['alias'] != ZBX_GUEST_USER && zbx_empty($_REQUEST['password1'])) {
		show_error_message(_('Password should not be empty'));
	}
	else {
		$user = [
			'userid' => CWebUser::$data['userid'],
			'url' => getRequest('url'),
			'autologin' => getRequest('autologin', 0),
			'autologout' => hasRequest('autologout_visible') ? getRequest('autologout') : '0',
			'theme' => getRequest('theme'),
			'refresh' => getRequest('refresh'),
			'rows_per_page' => getRequest('rows_per_page')
		];

		if (hasRequest('password1')) {
			$user['passwd'] = getRequest('password1');
		}

		if (CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER) {
			$user['user_medias'] = [];

			foreach (getRequest('user_medias', []) as $media) {
				$user['user_medias'][] = [
					'mediatypeid' => $media['mediatypeid'],
					'sendto' => $media['sendto'],
					'active' => $media['active'],
					'severity' => $media['severity'],
					'period' => $media['period']
				];
			}
		}

		if (hasRequest('lang')) {
			$user['lang'] = getRequest('lang');
		}

		DBstart();
		$result = updateMessageSettings(getRequest('messages', []));

		$result = $result && (bool) API::User()->update($user);

		$result = DBend($result);

		if ($result) {
			ob_end_clean();

			redirect(ZBX_DEFAULT_URL);
		}
		else {
			show_messages($result, _('User updated'), _('Cannot update user'));
		}
	}
}

ob_end_flush();

/*
 * Display
 */
$config = select_config();

$data = getUserFormData(CWebUser::$data['userid'], $config, true);
$data['userid'] = CWebUser::$data['userid'];
$data['name'] = CWebUser::$data['name'];
$data['surname'] = CWebUser::$data['surname'];
$data['alias'] = CWebUser::$data['alias'];
$data['form'] = getRequest('form');
$data['form_refresh'] = getRequest('form_refresh', 0);

// render view
$usersView = new CView('administration.users.edit', $data);
$usersView->render();
$usersView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
