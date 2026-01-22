<?php

/**
 * -------------------------------------------------------------------------
 * Borgbase plugin for GLPI
 * Copyright (C) 2022 - 2026 by the TICGAL Team.
 * https://www.tic.gal/
 * -------------------------------------------------------------------------
 * LICENSE
 * This file is part of the Borgbase plugin.
 * Borgbase plugin is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 * Borgbase plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with Borgbase. If not, see <http://www.gnu.org/licenses/>.
 * --------------------------------------------------------------------------
 * @package  Borgbase
 * @author    the TICGAL team
 * @copyright Copyright (C) 2022 - 2026 TICGAL team
 * @license   AGPL License 3.0 or (at your option) any later version
 * http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * @link      https://www.tic.gal/
 * @since     2022
 * ----------------------------------------------------------------------
 */

/**
 * Borgbase Relation
 */
class PluginBorgbaseRelation extends CommonDBRelation
{
    /**
     * {@inheritDoc}
     */
    public static function getTypeName($nb = 0): string
    {
        return 'Borgbase Relation';
    }

    /**
     * install
     *
     * @param  Migration $migration
     * @return void
     */
    public static function install(Migration $migration): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $default_charset = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

        $table = (new DbUtils())->getTableForItemtype('PluginBorgbaseRelation');
        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");

            $query = "CREATE TABLE `$table` (
                `id`         				    INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
                `items_id`                      INT {$default_key_sign} NOT NULL DEFAULT '0',
                `itemtype`                      VARCHAR(255) DEFAULT NULL,
                `plugin_borgbase_borgbases_id`  INT {$default_key_sign} NOT NULL DEFAULT '0',
                PRIMARY KEY  (`id`),
                KEY `items_id` (`items_id`),
                KEY `itemtype` (`itemtype`,`items_id`),
                KEY `plugin_borgbase_borgbases_id` (`plugin_borgbase_borgbases_id`)
            ) ENGINE=InnoDB
                DEFAULT CHARSET={$default_charset}
                COLLATE={$default_collation}";
            $DB->doQueryOrDie($query, $DB->error());
        }
    }

    /**
     * uninstall
     *
     * @param  Migration $migration
     * @return void
     */
    public static function uninstall(Migration $migration): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $table = self::getTable();
        //$migration->displayMessage('Uninstalling ' . $table);
        //$DB->queryOrDie("DROP TABLE `$table`", $DB->error());
    }
}
