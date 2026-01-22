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
 * Provider
 */
class PluginBorgbaseProvider
{
    /**
     * usageHistory
     *
     * @param  mixed $params
     * @return array
     */
    public static function usageHistory($params = []): array
    {
        $data = [];

        $borgbase = new PluginBorgbaseBorgbase();
        $usages = $borgbase->getCurrentUsage();

        // New rows last
        krsort($usages);

        foreach ($usages as $usage) {
            $date = date('d/m', strtotime($usage['date']));
            $use = number_format($usage['usedGb'], 2, '.', '');

            $data[] = [
                'number' => $use,
                'label' => $date,
            ];
        }

        if (count($data) === 0) {
            $data = [
                'nodata' => true
            ];
        }

        $provide = [
            'data'  => $data,
            'label' => 'Borgbase - ' . __('Total Usage History') . ' (GB)',
            'icon'  => PluginBorgbaseBorgbase::getIcon()
        ];

        return $provide;
    }

    /**
     * usageQuotaPer
     *
     * @param  mixed $params
     * @return array
     */
    public static function usageQuotaPer($params = []): array
    {
        $data = [];

        $borgbase = new PluginBorgbaseBorgbase();
        $usages = $borgbase->getCurrentUsage();

        $currentUsage = $usages[0]['usedGb'];
        $limitUsage = $usages[0]['plan']['includedSize'] / 1000; //GB

        $calc = ($currentUsage * 100) / $limitUsage;
        $percentage = number_format($calc, 2);

        $data[] = [
            'number' => $percentage,
            'label' => __('Storage usage', 'borgbase'),
        ];

        // percentage can be greater than 100
        // borgbase has a plan of 1TB up to flexible 4TB for example
        if ($percentage < 100) {
            $data[] = [
                'number' => $limitUsage - $currentUsage,
                'label' => __('Free storage', 'borgbase'),
            ];
        }

        $provide = [
            'data'      => $data,
            'label'     => 'Borgbase - ' . __('Total Usage Percentage', 'borgbase') . ' (%)',
            'icon'      => PluginBorgbaseBorgbase::getIcon()
        ];

        return $provide;
    }

    /**
     * numberRepositories
     *
     * @param  mixed $params
     * @return array
     */
    public static function numberRepositories($params = []): array
    {
        $borgbase = new PluginBorgbaseBorgbase();
        $numRepos = count($borgbase->getRepoList());

        $provide = [
            'number'    => $numRepos,
            'label'     => 'Borgbase - ' . __('Number of Repositories', 'borgbase'),
            'icon'      => PluginBorgbaseBorgbase::getIcon()
        ];

        return $provide;
    }

    /**
     * currentUse
     *
     * @param  mixed $params
     * @return array
     */
    public static function currentUse($params = []): array
    {
        $borgbase = new PluginBorgbaseBorgbase();
        $currentUse = $borgbase->getCurrentUsage()[0]['usedGb'];

        $provide = [
            'number'    => $currentUse,
            'label'     => 'Borgbase - ' . __('Current Use', 'borgbase') . ' (GB)',
            'icon'  => PluginBorgbaseBorgbase::getIcon()
        ];

        return $provide;
    }

    /**
     * numberOfLinkedRepositories
     *
     * @param  mixed $params
     * @return array
     */
    public static function numberOfLinkedRepositories($params = []): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $sub_query = new \Glpi\DBAL\QuerySubQuery([
            'SELECT' => 'items_id',
            'FROM'   => PluginBorgbaseRelation::getTable()
        ]);

        $iterator = $DB->request([
            'SELECT' => ['COUNT DISTINCT' => 'id as count'],
            'FROM' => 'glpi_computers',
            'WHERE' => [
                'id' => $sub_query
            ]
        ]);

        $result = $iterator->current();
        $linkedRepositories = $result['count'];

        $search_url = Computer::getSearchURL();
        $search_criteria = [];
        if (Session::haveRight(PluginBorgbaseBorgbase::$rightname, READ)) {
            $search_criteria['criteria'][] = [
                'link'       => '',
                'field'      => PluginBorgbaseBorgbase::$sopt,
                'searchtype' => 'notcontains',
                'value'      => 'null'
            ];
        }

        $url = $search_url . (str_contains($search_url, '?') ? '&' : '?') . Toolbox::append_params([
            $search_criteria,
            'reset' => 'reset',
        ]);

        $provide = [
            'number'    => $linkedRepositories,
            'url'       => $url,
            'label'     => 'Borgbase - ' . __('Number of Computers with a Repo Linked', 'borgbase'),
            'icon'      => PluginBorgbaseBorgbase::getIcon()
        ];

        return $provide;
    }
}
