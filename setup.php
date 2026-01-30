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

use Glpi\Plugin\Hooks;

define('PLUGIN_BORGBASE_VERSION', '2.0.0-beta3');
define("PLUGIN_BORGBASE_MIN_GLPI_VERSION", "11.0");
define("PLUGIN_BORGBASE_MAX_GLPI_VERSION", "12.0");
define("PLUGIN_BORGBASE_ICON", "fa-solid fa-hard-drive");

/**
 * plugin_init_borgbase
 *
 * @return void
 */
function plugin_init_borgbase(): void
{
    /** @var array $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    $plugin = new Plugin();
    if ($plugin->isActivated('borgbase')) {
        Plugin::registerClass(
            PluginBorgbaseConfig::class,
            [
                'addtabon' => [
                    'Config',
                ]
            ]
        );
        if (Session::haveRight(PluginBorgbaseBorgbase::$rightname, READ)) {
            Plugin::registerClass(
                PluginBorgbaseBorgbase::class,
                [
                    'addtabon' => [
                        'Computer',
                    ]
                ]
            );
        }
        Plugin::registerClass(
            PluginBorgbaseRelation::class,
            [
                'addtabon' => [
                    'Computer',
                ]
            ]
        );
        Plugin::registerClass(
            PluginBorgbaseProfile::class,
            [
                'addtabon' => [
                    'Profile',
                ]
            ]
        );

        CronTask::register(
            PluginBorgbaseCron::class,
            'borgbaseUpdate',
            DAY_TIMESTAMP,
            [
                'comment' => '',
                'mode' => CronTask::MODE_EXTERNAL
            ]
        );

        // Config page
        if (Session::haveRight('config', UPDATE)) {
            $PLUGIN_HOOKS['config_page']['borgbase'] = 'front/config.form.php';
        }
        $PLUGIN_HOOKS['dashboard_cards']['borgbase'] = ['PluginBorgbaseDashboard', 'dashboardCards'];
    }
}

/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array
 */
function plugin_version_borgbase(): array
{
    return [
        'name'      => 'Borgbase',
        'version'   => PLUGIN_BORGBASE_VERSION,
        'author'    => '<a href="https://tic.gal">TICGAL</a>',
        'homepage'  => 'https://tic.gal',
        'license'   => 'AGPLv3+',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_BORGBASE_MIN_GLPI_VERSION,
                'max' => PLUGIN_BORGBASE_MAX_GLPI_VERSION,
            ],
        ],
    ];
}
