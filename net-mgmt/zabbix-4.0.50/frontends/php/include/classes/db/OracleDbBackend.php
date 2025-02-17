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


/**
 * Database backend class for Oracle.
 */
class OracleDbBackend extends DbBackend {

	/**
	 * Check if 'dbversion' table exists.
	 *
	 * @return boolean
	 */
	protected function checkDbVersionTable() {
		$table_exists = DBfetch(DBselect("SELECT table_name FROM user_tables WHERE table_name='DBVERSION'"));

		if (!$table_exists) {
			$this->setError(_s('Unable to determine current Zabbix database version: %1$s.',
				_s('the table "%1$s" was not found', 'dbversion')
			));

			return false;
		}

		return true;
	}

	/**
	 * Create INSERT SQL query.
	 * Creation example:
	 *	BEGIN
	 *	INSERT INTO usrgrp (usrgrpid, name, gui_access, users_status, debug_mode)
	 *		VALUES ('20', 'admins', '1', '0', '1');
	 *	INSERT INTO usrgrp (usrgrpid, name, gui_access, users_status, debug_mode)
	 *		VALUES ('21', 'users', '0', '0', '0');
	 *  END;
	 */
	public function createInsertQuery($table, array $fields, array $values) {
		$sql = 'BEGIN';
		$fields = '('.implode(',', $fields).')';
		foreach ($values as $row) {
			$sql .= ' INSERT INTO '.$table.' '.$fields.' VALUES ('.implode(',', array_values($row)).');';
		}
		$sql .= ' END;';

		return $sql;
	}

	/**
	 * Check database and table fields encoding.
	 *
	 * @return bool
	 */
	public function checkEncoding() {
		return $this->checkDatabaseEncoding();
	}

	/**
	 * Check database schema encoding. On error will set warning message.
	 *
	 * @return bool
	 */
	protected function checkDatabaseEncoding() {
		$row = DBfetch(DBselect('SELECT value, parameter FROM NLS_DATABASE_PARAMETERS'.
			' WHERE '.dbConditionString('parameter', ['NLS_CHARACTERSET', 'NLS_NCHAR_CHARACTERSET']).
				' AND value!='.zbx_dbstr(ZBX_DB_ORACLE_ALLOWED_CHARSET)
		));

		if ($row) {
			$this->setWarning((_s('Incorrect parameter "%1$s" value: %2$s.',
				$row['parameter'], _s('"%1$s" instead "%2$s"', $row['value'], ZBX_DB_ORACLE_ALLOWED_CHARSET)
			)));
		}

		return !$row;
	}
}
