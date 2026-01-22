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
 * Cron
 */
class PluginBorgbaseCron extends CommonDBTM
{
    /**
    * cronInfo
    *
    * @param  string $name
    * @return array
    */
    public static function cronInfo($name): array
    {
        switch ($name) {
            case 'borgbaseUpdate':
                return ['description' => __('Update borgbase records', 'borgbase')];
        }
        return array();
    }

    /**
    * cronBorgbaseUpdate
    *
    * @param  CronTask $task
    * @return boolean
    */
    public static function cronBorgbaseUpdate(CronTask $task = null): bool
    {
        /** @var \DBmysql $DB */
        global $DB;

        $borgbase = new PluginBorgbaseBorgbase();
        $table = PluginBorgbaseBorgbase::getTable();
        foreach ($DB->request(['SELECT' => 'borg_id', 'FROM' => $table]) as $id => $row) {
            if ($borgbase->reloadRepo($row['borg_id'])) {
                $task->addVolume(1);
            }
        }

        return true;
    }
}
