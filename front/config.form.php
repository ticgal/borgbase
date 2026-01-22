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

include "../../../inc/includes.php";

$plugin = new Plugin();
if (!$plugin->isInstalled('borgbase') || !$plugin->isActivated('borgbase')) {
    Html::displayNotFoundError();
}

$borgbase = new PluginBorgbaseConfig();

Session::checkRight('config', UPDATE);
if (isset($_POST['reload'])) {
    $borgbase->linkAvailableRepos();
}

if (isset($_POST['update'])) {
    $borgbase->check($_POST['id'], UPDATE);
    $glpikey = new GLPIKey();
    if ($_POST['apikey']) {
        $_POST["apikey"] = $glpikey->encrypt($_POST["apikey"]);
    } else {
        unset($_POST['apikey']);
    }
    $borgbase->update($_POST);

    // Checking again, apikey needs to be updated
    if (isset($_POST['apikey']) && $_POST['apikey']) {
        $borgbase->linkAvailableRepos();
    }

    Html::back();
}

/** @var array $CFG_GLPI */
global $CFG_GLPI;

$redirect = $CFG_GLPI["root_doc"] . "/front/config.form.php";
$redirect .= "?forcetab=" . urlencode('PluginBorgbaseConfig$1');
Html::redirect($redirect);
