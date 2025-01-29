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
 * Database backend class for MySQL.
 */
class MysqlDbBackend extends DbBackend {

	/**
	 * Check if 'dbversion' table exists.
	 *
	 * @return bool
	 */
	protected function checkDbVersionTable() {
		$table_exists = DBfetch(DBselect("SHOW TABLES LIKE 'dbversion'"));

		if (!$table_exists) {
			$this->setError(_s('Unable to determine current Zabbix database version: %1$s.',
				_s('the table "%1$s" was not found', 'dbversion')
			));

			return false;
		}

		return true;
	}

	/**
	 * Check database and table fields encoding.
	 *
	 * @return bool
	 */
	public function checkEncoding() {
		global $DB;

		return $this->checkDatabaseEncoding($DB) && $this->checkTablesEncoding($DB);
	}

	/**
	 * Check database schema encoding. On error will set warning message.
	 *
	 * @param array $DB  Array of database settings, same as global $DB.
	 *
	 * @return bool
	 */
	protected function checkDatabaseEncoding(array $DB) {
		$allowed_charsets = explode(',', ZBX_DB_MYSQL_ALLOWED_CHARSETS);

		$row = DBfetch(DBselect('SELECT default_character_set_name db_charset FROM information_schema.schemata'.
			' WHERE schema_name='.zbx_dbstr($DB['DATABASE'])
		));

		if ($row && !in_array(strtoupper($row['db_charset']), $allowed_charsets)) {
			$this->setWarning(_s('Incorrect default charset for Zabbix database: %1$s.',
				_s('"%1$s" instead "%2$s"', $row['db_charset'], ZBX_DB_MYSQL_ALLOWED_CHARSETS)
			));
			return false;
		}

		return true;
	}

	/**
	 * Check tables schema encoding. On error will set warning message.
	 *
	 * @param array $DB  Array of database settings, same as global $DB.
	 *
	 * @return bool
	 */
	protected function checkTablesEncoding(array $DB) {
		$allowed_charsets = explode(',', ZBX_DB_MYSQL_ALLOWED_CHARSETS);
		$allowed_collations = explode(',', ZBX_DB_MYSQL_ALLOWED_COLLATIONS);

		// Aliasing table_name to ensure field name is lowercase.
		$tables = DBfetchColumn(DBSelect('SELECT table_name AS table_name FROM information_schema.columns'.
			' WHERE table_schema='.zbx_dbstr($DB['DATABASE']).
				' AND '.dbConditionString('table_name', array_keys(DB::getSchema())).
				' AND '.dbConditionString('data_type', ['text', 'varchar', 'longtext']).
				' AND ('.
					dbConditionString('UPPER(character_set_name)', $allowed_charsets, true).
					' OR '.dbConditionString('collation_name', $allowed_collations, true).
				')'
		), 'table_name');

		if ($tables) {
			$tables = array_unique($tables);
			$this->setWarning(_n('Unsupported charset or collation for table: %1$s.',
				'Unsupported charset or collation for tables: %1$s.',
				implode(', ', $tables), implode(', ', $tables), count($tables)
			));
			return false;
		}

		return true;
	}
}
