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

use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Exception\Http\NotFoundHttpException;
use GlpiPlugin\Borgbase\Borgbase;

if (!Plugin::isPluginActive('borgbase')) {
    throw new NotFoundHttpException();
}

$actions = [
    'reload' => UPDATE,
    'unlink' => PURGE,
    'assoc'  => CREATE,
];
$action = null;
foreach (array_keys($actions) as $name) {
    if (isset($_POST[$name])) {
        $action = $name;
        break;
    }
}
if ($action === null) {
    Html::back();
}

Session::checkRight(Borgbase::$rightname, $actions[$action]);

// The computer must exist and be editable by the user (entity): every action
// writes its link data or its history
$computerId = (int) ($action === 'assoc' ? ($_POST['id'] ?? 0) : ($_POST['computerId'] ?? 0));
$computer = new Computer();
if ($computerId <= 0 || !$computer->getFromDB($computerId)) {
    throw new NotFoundHttpException();
}
if (!$computer->can($computerId, UPDATE)) {
    throw new AccessDeniedHttpException();
}

$repoId = $_POST['repoId'] ?? null;
if (!Borgbase::isValidRepoId($repoId)) {
    throw new BadRequestHttpException();
}

$borgbase = new Borgbase();
$historyText = sprintf(__('%2$s, by user %1$s'), $_SESSION['glpiname'], $repoId);

switch ($action) {
    case 'reload':
        if ($borgbase->getComputerLink($computerId, $repoId) === null) {
            throw new NotFoundHttpException();
        }
        if (!$borgbase->reloadRepo($repoId)) {
            Session::addMessageAfterRedirect(__('Check API connection', 'borgbase'), false, ERROR);
        }
        break;

    case 'unlink':
        if (!$borgbase->unlinkRepo($computerId, $repoId)) {
            throw new NotFoundHttpException();
        }
        Borgbase::logHistory($computerId, Log::HISTORY_DEL_RELATION, $historyText);
        break;

    case 'assoc':
        $repo = $borgbase->getRepo($repoId);
        if ($repo === []) {
            Session::addMessageAfterRedirect(__('Repository not found', 'borgbase'), false, ERROR);
        } elseif ($borgbase->linkRepo($repo, $computerId)) {
            Borgbase::logHistory($computerId, Log::HISTORY_ADD_RELATION, $historyText);
        } else {
            Session::addMessageAfterRedirect(__('The repository or the computer is already linked', 'borgbase'), false, ERROR);
        }
        break;
}

Html::back();
