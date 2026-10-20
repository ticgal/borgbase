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

use CommonDBTM;
use CommonGLPI;
use DBConnection;
use Glpi\Application\View\TemplateRenderer;
use GLPIKey;
use Migration;
use Session;

/**
 * Borgbase Config
 */
class Config extends CommonDBTM
{
    public const DEFAULT_SERVER = 'https://api.borgbase.com/graphql';

    public static string $rightname = 'config';

    /**
     * {@inheritDoc}
     */
    protected static function itemTypeRequiresReauthentication(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public static function getTypeName($nb = 0): string
    {
        return 'Borgbase';
    }

    /**
     * No list page for this itemtype (/plugins/borgbase/front/config.php would
     * otherwise be served by the core generic list controller)
     *
     * @return bool
     */
    public static function canView(): bool
    {
        return false;
    }

    /**
     * Current configuration (single row, id 1)
     *
     * @return self
     */
    public static function getConfig(): self
    {
        $config = new self();
        $config->getFromDB(1);
        return $config;
    }

    /**
     * {@inheritDoc}
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if (!$withtemplate && $item instanceof \Config && Session::haveRight('config', UPDATE)) {
            return self::createTabEntry(self::getTypeName());
        }
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if ($item instanceof \Config) {
            self::getConfig()->showConfigForm();
            return true;
        }
        return false;
    }

    /**
     * Display the configuration form
     *
     * @return bool
     */
    public function showConfigForm(): bool
    {
        if (!$this->can(1, UPDATE)) {
            return false;
        }

        $connected = str_contains(Borgbase::request('{isAuthenticated}'), 'true');

        TemplateRenderer::getInstance()->display('@borgbase/config.html.twig', [
            'item'        => $this,
            'connection'  => $connected,
            'has_apikey'  => !empty($this->fields['apikey']),
            'params'      => ['canedit' => true],
            'options'     => ['full_width' => true],
            'labels'      => [
                'server'      => __('Server endpoint', 'borgbase'),
                'apikey'      => __('Authentication key', 'borgbase'),
                'blank'       => __('Clear the saved key', 'borgbase'),
                'match'       => __('Computer name matches exactly with repository name', 'borgbase'),
                'reload'      => __('Link repositories', 'borgbase'),
                'connected'   => __('Established connection', 'borgbase'),
                'unconnected' => __('Check API connection', 'borgbase'),
            ],
        ]);

        return true;
    }

    /**
     * Server endpoint must be an https URL with a host
     *
     * @param  string $url
     * @return bool
     */
    public static function isValidServer(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = parse_url($url);
        return strtolower($parts['scheme'] ?? '') === 'https'
            && !empty($parts['host'])
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }

    /**
     * Link every repository whose name matches a computer name
     *
     * @return int number of linked repositories
     */
    public function linkAvailableRepos(): int
    {
        /** @var \DBmysql $DB */
        global $DB;

        $borgbase = new Borgbase();
        $linkedRepos = 0;

        foreach ($borgbase->getRepoList() as $repo) {
            if (!Borgbase::isValidRepo($repo)) {
                continue;
            }
            $computer = $DB->request([
                'SELECT' => 'id',
                'FROM'   => 'glpi_computers',
                'WHERE'  => [
                    'name'        => $repo['name'],
                    'is_deleted'  => 0,
                    'is_template' => 0,
                ],
                'LIMIT'  => 1,
            ])->current();

            if ($computer && $borgbase->linkRepo($repo, (int) $computer['id'])) {
                $linkedRepos++;
            }
        }

        return $linkedRepos;
    }

    /**
     * {@inheritDoc}
     */
    public function prepareInputForUpdate($input): false|array
    {
        $clean = ['id' => 1];

        if (array_key_exists('match', $input)) {
            $clean['match'] = (int) (bool) $input['match'];
        }

        $server_changed = false;
        if (array_key_exists('server', $input)) {
            $server = trim((string) $input['server']);
            if (!self::isValidServer($server)) {
                Session::addMessageAfterRedirect(
                    __('The server endpoint must be a valid https URL', 'borgbase'),
                    false,
                    ERROR,
                );
                return false;
            }
            $clean['server'] = $server;
            $server_changed = $server !== ($this->fields['server'] ?? '');
        }

        if (!empty($input['apikey'])) {
            $clean['apikey'] = (new GLPIKey())->encrypt((string) $input['apikey']);
        } elseif (!empty($input['_blank_apikey'])) {
            $clean['apikey'] = '';
        } elseif ($server_changed && !empty($this->fields['apikey'])) {
            // Never send the stored key to a server it was not entered for
            $clean['apikey'] = '';
            Session::addMessageAfterRedirect(
                __('The server endpoint has changed: enter the authentication key again', 'borgbase'),
                false,
                WARNING,
            );
        }

        return $clean;
    }

    /**
     * {@inheritDoc}
     */
    public function prepareInputForAdd($input): false|array
    {
        // Single configuration row, created at install
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public static function getIcon(): string
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
            $query = "CREATE TABLE IF NOT EXISTS `$table` (
                `id` int {$default_key_sign} NOT NULL auto_increment,
                `server` VARCHAR(512) NOT NULL DEFAULT '',
                `apikey` VARCHAR(640) NOT NULL DEFAULT '',
                `match` tinyint(1) NOT NULL DEFAULT '0',
                `debug` tinyint(1) NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->doQuery($query);
        }

        if (countElementsInTable($table, ['id' => 1]) === 0) {
            $DB->insert($table, [
                'id'     => 1,
                'server' => self::DEFAULT_SERVER,
                'apikey' => '',
                'match'  => 1,
                'debug'  => 0,
            ]);
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
        if ($DB->tableExists($table)) {
            $migration->displayMessage('Uninstalling ' . $table);
            $DB->dropTable($table);
        }
    }
}
