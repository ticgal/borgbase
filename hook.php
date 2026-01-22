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
 * Plugin install process
 *
 * @return boolean
 */
function plugin_borgbase_install(): bool
{
    $migration = new Migration(PLUGIN_BORGBASE_VERSION);

    // Parse inc directory
    foreach (glob(dirname(__FILE__) . '/inc/*') as $filepath) {
        // Load *.class.php files and get the class name
        if (preg_match("/inc.(.+)\.class.php/", $filepath, $matches)) {
            $classname = 'PluginBorgbase' . ucfirst($matches[1]);
            include_once $filepath;
            // If the install method exists, load it
            if (method_exists($classname, 'install')) {
                $classname::install($migration);
            }
        }
    }

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

    // Parse inc directory
    foreach (glob(dirname(__FILE__) . '/inc/*') as $filepath) {
        // Load *.class.php files and get the class name
        if (preg_match("/inc.(.+)\.class.php/", $filepath, $matches)) {
            $classname = 'PluginBorgbase' . ucfirst($matches[1]);
            include_once $filepath;
            // If the install method exists, load it
            if (method_exists($classname, 'uninstall')) {
                $classname::uninstall($migration);
            }
        }
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
    return PluginBorgbaseBorgbase::getAddSearchOptions($itemtype);
}
