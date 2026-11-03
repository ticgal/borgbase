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

namespace GlpiPlugin\Borgbase;

use CommonGLPI;
use CronTask;
use Migration;

/**
 * Cron
 */
class Cron extends CommonGLPI
{
    /**
     * {@inheritDoc}
     */
    public static function getTypeName($nb = 0): string
    {
        return 'Borgbase';
    }

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
        return [];
    }

    /**
     * Refresh the data of every linked repository
     *
     * @param  CronTask $task
     * @return int 1 if something was refreshed, 0 otherwise
     */
    public static function cronBorgbaseUpdate(CronTask $task): int
    {
        /** @var \DBmysql $DB */
        global $DB;

        $borgbase = new Borgbase();
        $updated = 0;
        foreach ($DB->request(['SELECT' => 'borg_id', 'FROM' => Borgbase::getTable()]) as $row) {
            if ($borgbase->reloadRepo($row['borg_id'])) {
                $updated++;
            }
        }
        $task->addVolume($updated);

        return $updated > 0 ? 1 : 0;
    }

    /**
     * install
     *
     * @param  Migration $migration
     * @return void
     */
    public static function install(Migration $migration): void
    {
        CronTask::register(
            self::class,
            'borgbaseUpdate',
            DAY_TIMESTAMP,
            [
                'comment' => '',
                'mode'    => CronTask::MODE_EXTERNAL,
            ],
        );
    }

    /**
     * uninstall
     *
     * @param  Migration $migration
     * @return void
     */
    public static function uninstall(Migration $migration): void
    {
        // Plugin crontasks are removed by the core on uninstall
    }
}
