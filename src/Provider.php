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

use Computer;
use Glpi\DBAL\QuerySubQuery;
use Session;
use Toolbox;

/**
 * Dashboard providers
 */
class Provider
{
    /**
     * Usage rows from the API, newest last
     *
     * @return array
     */
    private static function getUsages(): array
    {
        if (!Session::haveRight(Borgbase::$rightname, READ)) {
            return [];
        }
        return array_values(array_filter(
            (new Borgbase())->getCurrentUsage(),
            static fn($usage) => is_array($usage) && is_numeric($usage['usedGb'] ?? null),
        ));
    }

    /**
     * usageHistory
     *
     * @param  mixed $params
     * @return array
     */
    public static function usageHistory($params = []): array
    {
        $label = 'Borgbase - ' . __('Total Usage History', 'borgbase') . ' (GB)';
        if (!Session::haveRight(Borgbase::$rightname, READ)) {
            // Line/bar widgets need a list in 'data' (see below)
            return ['data' => [], 'label' => $label, 'icon' => Borgbase::getIcon()];
        }

        $data = [];

        $usages = self::getUsages();
        // New rows last
        krsort($usages);

        foreach ($usages as $usage) {
            $time = strtotime((string) ($usage['date'] ?? ''));
            $data[] = [
                'number' => number_format((float) $usage['usedGb'], 2, '.', ''),
                'label'  => $time ? date('d/m', $time) : '',
            ];
        }

        // Line/bar widgets iterate 'data' as a list: an empty list renders an
        // empty chart, ['nodata' => true] makes Widget::simpleLine() fail
        return [
            'data'  => $data,
            'label' => $label,
            'icon'  => Borgbase::getIcon(),
        ];
    }

    /**
     * usageQuotaPer
     *
     * @param  mixed $params
     * @return array
     */
    public static function usageQuotaPer($params = []): array
    {
        $label = 'Borgbase - ' . __('Total Usage Percentage', 'borgbase') . ' (%)';
        if (!Session::haveRight(Borgbase::$rightname, READ)) {
            return ['data' => ['nodata' => true], 'label' => $label, 'icon' => Borgbase::getIcon()];
        }

        $data = [];

        $usages = self::getUsages();
        $currentUsage = (float) ($usages[0]['usedGb'] ?? 0);
        $limitUsage = (float) ($usages[0]['plan']['includedSize'] ?? 0) / 1000; //GB

        if ($limitUsage > 0) {
            $percentage = ($currentUsage * 100) / $limitUsage;
            $data[] = [
                'number' => number_format($percentage, 2, '.', ''),
                'label'  => __('Storage usage', 'borgbase'),
            ];

            // percentage can be greater than 100
            // borgbase has a plan of 1TB up to flexible 4TB for example
            if ($percentage < 100) {
                $data[] = [
                    'number' => $limitUsage - $currentUsage,
                    'label'  => __('Free storage', 'borgbase'),
                ];
            }
        } else {
            $data = ['nodata' => true];
        }

        return [
            'data'      => $data,
            'label'     => $label,
            'icon'      => Borgbase::getIcon(),
        ];
    }

    /**
     * numberRepositories
     *
     * @param  mixed $params
     * @return array
     */
    public static function numberRepositories($params = []): array
    {
        $numRepos = Session::haveRight(Borgbase::$rightname, READ)
            ? count((new Borgbase())->getRepoList())
            : 0;

        return [
            'number'    => $numRepos,
            'label'     => 'Borgbase - ' . __('Number of Repositories', 'borgbase'),
            'icon'      => Borgbase::getIcon(),
        ];
    }

    /**
     * currentUse
     *
     * @param  mixed $params
     * @return array
     */
    public static function currentUse($params = []): array
    {
        $label = 'Borgbase - ' . __('Current Use', 'borgbase') . ' (GB)';
        if (!Session::haveRight(Borgbase::$rightname, READ)) {
            return ['number' => 0, 'label' => $label, 'icon' => Borgbase::getIcon()];
        }

        return [
            'number'    => (float) (self::getUsages()[0]['usedGb'] ?? 0),
            'label'     => $label,
            'icon'      => Borgbase::getIcon(),
        ];
    }

    /**
     * Computers of the active entities with a linked repository
     *
     * @param  mixed $params
     * @return array
     */
    public static function numberOfLinkedRepositories($params = []): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $linkedRepositories = 0;
        if (Session::haveRight(Borgbase::$rightname, READ)) {
            $result = $DB->request([
                'SELECT' => ['COUNT DISTINCT' => 'id as count'],
                'FROM'   => Computer::getTable(),
                'WHERE'  => [
                    'id'          => new QuerySubQuery([
                        'SELECT' => 'items_id',
                        'FROM'   => Relation::getTable(),
                        'WHERE'  => ['itemtype' => Computer::class],
                    ]),
                    'is_deleted'  => 0,
                    'is_template' => 0,
                ] + getEntitiesRestrictCriteria(Computer::getTable()),
            ])->current();
            $linkedRepositories = (int) ($result['count'] ?? 0);
        }

        $search_url = Computer::getSearchURL();
        $search_criteria = [
            'criteria' => [
                [
                    'link'       => 'AND',
                    'field'      => Borgbase::$sopt,
                    'searchtype' => 'notcontains',
                    'value'      => 'null',
                ],
            ],
            'reset' => 'reset',
        ];

        return [
            'number'    => $linkedRepositories,
            'url'       => $search_url . (str_contains($search_url, '?') ? '&' : '?') . Toolbox::append_params($search_criteria),
            'label'     => 'Borgbase - ' . __('Number of Computers with a Repo Linked', 'borgbase'),
            'icon'      => Borgbase::getIcon(),
        ];
    }
}
