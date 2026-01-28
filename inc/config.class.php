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

use Glpi\Application\View\TemplateRenderer;

/**
 * Borgbase Config
 */
class PluginBorgbaseConfig extends CommonDBTM
{
    public static $rightname = 'config';

    /**
     * {@inheritDoc}
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if (!$withtemplate) {
            if ($item->getType() == 'Config') {
                return self::createTabEntry('Borgbase');
            }
        }
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if ($item->getType() == 'Config') {
            $config = new self();
            $config->showFormExample();
            return true;
        }
        return false;
    }

    /**
     * showFormExample
     *
     * @return bool
     */
    public function showFormExample(): bool
    {
        if (!Session::haveRight("config", UPDATE)) {
            return false;
        }

        $config = new PluginBorgbaseConfig();
        $config->getFromDB(1);

        $connect = false;
        $msg = "<span class='text-muted'><i class='me-2 fa fa-check'></i>";
        $msg .= __('Established connection', 'borgbase') . "</span>";
        $options = [
            'full_width' => true
        ];

        $labels = [
            'server' => __('Server endpoint', 'borgbase'),
            'apikey' => __('Authentication key', 'borgbase'),
            'match' => __('Computer name matches exactly with repository name', 'borgbase'),
            'reload' => __('Link repositories', 'borgbase')
        ];

        $borgbase = new PluginBorgbaseBorgbase();
        $req = $borgbase->request('{isAuthenticated}');
        if (str_contains($req, 'true')) {
            $connect = true;
        }

        $placeholder = '';
        if (!empty($config->fields['apikey'])) {
            $placeholder = str_repeat('*', 20);
        }

        $templatePath = "@borgbase/config.html.twig";
        TemplateRenderer::getInstance()->display(
            $templatePath,
            [
                'item'          => $config,
                'connection'    => $connect,
                'labels'        => $labels,
                'msg'           => $msg,
                'options'       => $options,
                'placeholder'   => $placeholder
            ]
        );

        return true;
    }

    /**
     * Summary of linkAvailableRepos
     * @return int
     */
    public function linkAvailableRepos(): int
    {
        /** @var \DBmysql $DB */
        global $DB;

        $borgbase = new PluginBorgbaseBorgbase();
        $repoList = $borgbase->getRepoList();
        $linkedRepos = 0;

        foreach ($repoList as $repo) {
            $req = $DB->request([
                'SELECT' => 'id',
                'FROM' => 'glpi_computers',
                'WHERE' => [
                    'name' => $repo['name']
                ]
            ]);

            $computer = $req->current();
            if ($computer) {
                $linked = $borgbase->linkRepo($repo, $computer['id']);
                if ($linked) {
                    $linkedRepos++;
                }
            }
        }

        return $linkedRepos;
    }

    public function prepareInputForUpdate($input): false|array
    {
        if (isset($input['_blank_apikey']) && $input['_blank_apikey']) {
            $input['apikey'] = '';
        }
        return $input;
    }

    public static function getIcon()
    {
        return PLUGIN_BORGBASE_ICON;
    }

    /**
     * install
     *
     * @param  Migration $migration
     * @return void
     */
    public static function install(Migration $migration): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $default_charset = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

        $table = self::getTable();
        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");
            $query = "CREATE TABLE IF NOT EXISTS $table (
				`id` int {$default_key_sign} NOT NULL auto_increment,
				`server` VARCHAR(512) NOT NULL DEFAULT '',
				`apikey` VARCHAR(640) NOT NULL DEFAULT '',
				`match` tinyint(1) NOT NULL DEFAULT '0',
				`debug` tinyint(1) NOT NULL DEFAULT '0',
				PRIMARY KEY (`id`)
			)ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->doQuery($query);

            // Default config
            $DB->insert(
                $table,
                [
                    'server' => 'https://api.borgbase.com/graphql',
                    'apikey' => '',
                    'match' => '1',
                    'debug' => '0',
                ]
            );
        }
    }

    /**
     * uninstall
     *
     * @param  Migration $migration
     * @return void
     */
    public static function uninstall(Migration $migration): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $table = self::getTable();
        //$migration->displayMessage('Uninstalling ' . $table);
        //$DB->queryOrDie("DROP TABLE `$table`", $DB->error());
    }
}
