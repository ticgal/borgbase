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
use GlpiPlugin\Borgbase\Borgbase;
use GlpiPlugin\Borgbase\Config;
use GlpiPlugin\Borgbase\Dashboard;
use GlpiPlugin\Borgbase\Profile;

define('PLUGIN_BORGBASE_VERSION', '3.0.0-beta.2');
define("PLUGIN_BORGBASE_MIN_GLPI_VERSION", "12.0.0");
define("PLUGIN_BORGBASE_MAX_GLPI_VERSION", "12.1.0");
define("PLUGIN_BORGBASE_ICON", "ti ti-cloud-upload");

/**
 * plugin_init_borgbase
 *
 * @return void
 */
function plugin_init_borgbase(): void
{
    /** @var array $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    if (!Plugin::isPluginActive('borgbase')) {
        return;
    }

    Plugin::registerClass(Config::class, ['addtabon' => ['Config']]);
    Plugin::registerClass(Borgbase::class, ['addtabon' => ['Computer']]);
    Plugin::registerClass(Profile::class, ['addtabon' => ['Profile']]);

    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['borgbase'] = 'front/config.form.php';
    }
    $PLUGIN_HOOKS[Hooks::DASHBOARD_CARDS]['borgbase'] = [Dashboard::class, 'dashboardCards'];
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
