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

use GlpiPlugin\Borgbase\Config;

if (!Plugin::isPluginActive('borgbase')) {
    throw new Glpi\Exception\Http\NotFoundHttpException();
}

Session::checkRight('config', UPDATE);
Config::checkReAuthenticationOrRedirect();

$config = Config::getConfig();

if (isset($_POST['update'])) {
    $config->check(1, UPDATE);
    $config->update(['id' => 1] + $_POST);

    // A new key has been saved: link the matching repositories
    if (!empty($_POST['apikey'])) {
        $config->linkAvailableRepos();
    }
    Html::back();
} elseif (isset($_POST['reload'])) {
    $config->check(1, UPDATE);
    $linked = $config->linkAvailableRepos();
    Session::addMessageAfterRedirect(
        sprintf(_n('%d repository linked', '%d repositories linked', $linked, 'borgbase'), $linked),
    );
    Html::back();
}

Html::redirect(\Config::getFormURL() . '?forcetab=' . urlencode(Config::class . '$1'));
