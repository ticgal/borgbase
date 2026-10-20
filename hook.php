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

use GlpiPlugin\Borgbase\Borgbase;
use GlpiPlugin\Borgbase\Config;
use GlpiPlugin\Borgbase\Cron;
use GlpiPlugin\Borgbase\Profile;
use GlpiPlugin\Borgbase\Relation;

/**
 * Plugin classes with install/uninstall steps, in install order
 *
 * @return array<class-string>
 */
function plugin_borgbase_classes(): array
{
    return [
        Config::class,
        Borgbase::class,
        Relation::class,
        Profile::class,
        Cron::class,
    ];
}

/**
 * Plugin install process
 *
 * @return boolean
 */
function plugin_borgbase_install(): bool
{
    /** @var \DBmysql $DB */
    global $DB;

    $migration = new Migration(PLUGIN_BORGBASE_VERSION);

    // Up to 2.x the classes lived in inc/ (PluginBorgbaseX): the itemtype stored
    // in glpi_crontasks, glpi_logs... is now the namespaced class name.
    foreach (plugin_borgbase_classes() as $classname) {
        $legacy = 'PluginBorgbase' . substr($classname, strrpos($classname, '\\') + 1);
        // Interrupted upgrade: legacy rows would break the unicity keys
        foreach (['glpi_crontasks', 'glpi_displaypreferences'] as $table) {
            if (countElementsInTable($table, ['itemtype' => $classname]) > 0) {
                $DB->delete($table, ['itemtype' => $legacy]);
            }
        }
        $migration->renameItemtype($legacy, $classname, false);
    }

    foreach (plugin_borgbase_classes() as $classname) {
        $classname::install($migration);
    }

    $migration->executeMigration();

    return true;
}

/**
 * Plugin uninstall process
 *
 * @return boolean
 */
function plugin_borgbase_uninstall(): bool
{
    $migration = new Migration(PLUGIN_BORGBASE_VERSION);

    foreach (array_reverse(plugin_borgbase_classes()) as $classname) {
        $classname::uninstall($migration);
    }

    return true;
}

/**
 * plugin_borgbase_getAddSearchOptions
 *
 * @param  mixed $itemtype
 * @return array
 */
function plugin_borgbase_getAddSearchOptions($itemtype): array
{
    return Borgbase::getAddSearchOptions($itemtype);
}
