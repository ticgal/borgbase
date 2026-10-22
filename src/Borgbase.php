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
use Computer;
use DBConnection;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Glpi\Toolbox\HttpClient;
use GLPIKey;
use Log;
use Migration;
use Session;
use Throwable;
use Toolbox;

/**
 * Borgbase API
 */
class Borgbase extends CommonDBTM
{
    public static string $rightname = 'plugin_borgbase_borgbase';

    public static $sopt = 1468;

    public bool $dohistory = true;

    /** BorgBase repository ids are short alphanumeric strings (column is VARCHAR(8)) */
    private const REPO_ID_PATTERN = '/^[A-Za-z0-9]{1,8}$/';

    /**
     * {@inheritDoc}
     */
    public static function getTypeName($nb = 0): string
    {
        return 'Borgbase';
    }

    /**
     * No list page for this itemtype (/plugins/borgbase/front/borgbase.php would
     * otherwise be served by the core generic list controller)
     *
     * @return bool
     */
    public static function canView(): bool
    {
        return false;
    }

    /**
     * getIcon
     *
     * @return string
     */
    public static function getIcon(): string
    {
        return PLUGIN_BORGBASE_ICON;
    }

    /**
     * {@inheritDoc}
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if ($item instanceof Computer && !$withtemplate && Session::haveRight(self::$rightname, READ)) {
            return self::createTabEntry(self::getTypeName());
        }
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if ($item instanceof Computer) {
            self::displayTab($item);
        }
        return true;
    }

    /**
     * Is $repo_id a well-formed BorgBase repository id?
     *
     * @param  mixed $repo_id
     * @return bool
     */
    public static function isValidRepoId($repo_id): bool
    {
        return is_string($repo_id) && preg_match(self::REPO_ID_PATTERN, $repo_id) === 1;
    }

    /**
     * Does the API answer look like a repository?
     *
     * @param  mixed $repo
     * @return bool
     */
    public static function isValidRepo($repo): bool
    {
        return is_array($repo)
            && self::isValidRepoId($repo['id'] ?? null)
            && is_string($repo['name'] ?? null);
    }

    /**
     * Run a GraphQL request against the configured BorgBase server
     *
     * Values must always go in $variables, never concatenated into $query.
     *
     * @param  string $query
     * @param  array  $variables
     * @return string raw JSON answer, '' on error
     */
    public static function request(string $query, array $variables = []): string
    {
        $config = Config::getConfig();
        $token  = $config->fields['apikey'] ?? '';
        $server = $config->fields['server'] ?? '';

        if ($query === '' || empty($token) || !Config::isValidServer($server)) {
            return '';
        }

        $body = ['query' => $query];
        if ($variables !== []) {
            $body['variables'] = $variables;
        }

        try {
            $client = new HttpClient(Config::class, [
                'timeout'       => 10,
                'max_duration'  => 20,
                'max_redirects' => 0,
            ]);
            $response = $client->post($server, [
                'auth_bearer' => (string) (new GLPIKey())->decrypt($token),
                'json'        => $body,
            ]);
            return $response->getContent();
        } catch (Throwable $e) {
            // The message never contains the token (sent as a header)
            Toolbox::logInFile('borgbase', sprintf('API request failed: %s', $e->getMessage()) . "\n");
        }

        return '';
    }

    /**
     * Repository fields requested to the API
     *
     * @return string
     */
    private static function getRepoCommonFields(): string
    {
        $fields = [
            'id',
            'name',
            'alertDays',
            'borgVersion',
            'region',
            'encryption',
            'createdAt',
            'lastModified',
            'compactionEnabled',
            'compactionInterval',
            'compactionIntervalUnit',
            'compactionHour',
            'compactionHourTimezone',
            'repoPath',
            'currentUsage',
        ];
        return implode(',', $fields);
    }

    /**
     * getRepo
     *
     * @param  string $repoId
     * @return array
     */
    public function getRepo($repoId): array
    {
        if (!self::isValidRepoId($repoId)) {
            return [];
        }
        $query = 'query($repoId: String!) { repo(repoId: $repoId) {' . self::getRepoCommonFields() . '}}';
        $repo = $this->getResponseKey($query, 'repo', ['repoId' => $repoId]);
        return self::isValidRepo($repo) ? $repo : [];
    }

    /**
     * getRepoByName
     *
     * @param  string $name
     * @return array
     */
    public function getRepoByName($name): array
    {
        $query = 'query($name: String) { repoList(name: $name) {' . self::getRepoCommonFields() . '}}';
        return $this->getResponseKey($query, 'repoList', ['name' => (string) $name]);
    }

    /**
     * getRepoList
     *
     * @return array
     */
    public function getRepoList(): array
    {
        $query = '{ repoList {' . self::getRepoCommonFields() . '}}';
        return $this->getResponseKey($query, 'repoList');
    }

    /**
     * getCurrentUsage
     *
     * @return array
     */
    public function getCurrentUsage(): array
    {
        $query = '{ overageList {usedGb,date,plan{includedSize}}}';
        return $this->getResponseKey($query, 'overageList');
    }

    /**
     * getResponseKey
     *
     * @param  string $query
     * @param  string $key
     * @param  array  $variables
     * @return array
     */
    private function getResponseKey(string $query, string $key, array $variables = []): array
    {
        $req = $this->formatRawRequest(self::request($query, $variables));
        return is_array($req[$key] ?? null) ? $req[$key] : [];
    }

    /**
     * Repositories linked to a computer
     *
     * @param  int $id
     * @return array
     */
    public function checkComputerRepo($id): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        return iterator_to_array($DB->request([
            'FROM'  => $this->getTable(),
            'WHERE' => ['computer_id' => (int) $id],
        ]), false);
    }

    /**
     * Computers linked to a repository
     *
     * @param  string $repoID
     * @return array
     */
    public function checkLink($repoID): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        return iterator_to_array($DB->request([
            'SELECT' => 'computer_id',
            'FROM'   => $this->getTable(),
            'WHERE'  => [
                'borg_id'     => (string) $repoID,
                'NOT'         => ['computer_id' => null],
            ],
        ]), false);
    }

    /**
     * Repository row linked to this computer, if any
     *
     * @param  int    $computerId
     * @param  string $repoId
     * @return array|null
     */
    public function getComputerLink(int $computerId, string $repoId): ?array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $row = $DB->request([
            'FROM'  => $this->getTable(),
            'WHERE' => [
                'computer_id' => $computerId,
                'borg_id'     => $repoId,
            ],
            'LIMIT' => 1,
        ])->current();

        return $row ?: null;
    }

    /**
     * Columns stored from an API repository
     *
     * @param  array $repo
     * @return array
     */
    private static function getRepoColumns(array $repo): array
    {
        $string = static fn($key) => isset($repo[$key]) && is_scalar($repo[$key]) ? (string) $repo[$key] : null;

        return [
            'alertDays'              => isset($repo['alertDays']) && is_numeric($repo['alertDays']) ? (int) $repo['alertDays'] : null,
            'borg_version'           => $string('borgVersion'),
            'is_encrypted'           => $string('encryption'),
            'region'                 => $string('region'),
            'createdAt'              => $string('createdAt'),
            'lastModified'           => $string('lastModified'),
            'compactionInterval'     => $string('compactionInterval'),
            'compactionIntervalUnit' => $string('compactionIntervalUnit'),
            'compactionHour'         => $string('compactionHour'),
            'compactionHourTimezone' => $string('compactionHourTimezone'),
            'repoPath'               => $string('repoPath'),
            'currentUsage'           => isset($repo['currentUsage']) && is_numeric($repo['currentUsage']) ? (string) $repo['currentUsage'] : '0',
        ];
    }

    /**
     * Link a repository to a computer
     *
     * @param  array $repo
     * @param  int   $id
     * @return boolean
     */
    public function linkRepo($repo, $id): bool
    {
        /** @var \DBmysql $DB */
        global $DB;

        $id = (int) $id;
        if (!self::isValidRepo($repo) || $id <= 0) {
            return false;
        }

        // One repository per computer, one computer per repository
        if (count($this->checkLink($repo['id'])) > 0 || count($this->checkComputerRepo($id)) > 0) {
            return false;
        }

        $DB->insert(
            $this->getTable(),
            [
                'borg_id'     => $repo['id'],
                'borg_name'   => $repo['name'],
                'computer_id' => $id,
            ] + self::getRepoColumns($repo),
        );

        $DB->insert(
            Relation::getTable(),
            [
                'items_id'  => $id,
                'itemtype'  => Computer::class,
                'plugin_borgbase_borgbases_id' => $DB->insertId(),
            ],
        );

        return true;
    }

    /**
     * Unlink the repository from this computer
     *
     * @param  int    $computerId
     * @param  string $repoId
     * @return bool
     */
    public function unlinkRepo($computerId, $repoId): bool
    {
        /** @var \DBmysql $DB */
        global $DB;

        $computerId = (int) $computerId;
        $repoId = (string) $repoId;
        $row = $this->getComputerLink($computerId, $repoId);
        if ($row === null) {
            return false;
        }

        $DB->delete(Relation::getTable(), [
            'plugin_borgbase_borgbases_id' => $row['id'],
            'itemtype'                     => Computer::class,
            'items_id'                     => $computerId,
        ]);
        // Only the row where the repository and the computer belong together
        $DB->delete($this->getTable(), [
            'id'          => $row['id'],
            'borg_id'     => $repoId,
            'computer_id' => $computerId,
        ]);

        return true;
    }

    /**
     * Refresh the stored data of a linked repository
     *
     * @param  string $repoId
     * @return boolean
     */
    public function reloadRepo($repoId): bool
    {
        /** @var \DBmysql $DB */
        global $DB;

        $repo = $this->getRepo($repoId);
        if ($repo === [] || !isset($repo['repoPath'])) {
            return false;
        }

        $DB->update(
            $this->getTable(),
            self::getRepoColumns($repo) + ['date_mod' => date('Y-m-d H:i:s')],
            ['borg_id' => $repoId],
        );

        return true;
    }

    /**
     * Add an entry to the history of the computer
     *
     * @param  int    $computerId
     * @param  int    $action Log::HISTORY_ADD_RELATION or Log::HISTORY_DEL_RELATION
     * @param  string $text
     * @return void
     */
    public static function logHistory(int $computerId, int $action, string $text): void
    {
        $changes = [0, '', ''];
        $changes[$action === Log::HISTORY_DEL_RELATION ? 1 : 2] = $text;
        Log::history($computerId, Computer::class, $changes, self::class, $action);
    }

    // Formating and display

    /**
     * The API always answers {"data":{"x"}}: return x
     *
     * @param  mixed $raw
     * @return array
     */
    public function formatRawRequest($raw): array
    {
        $array = json_decode((string) $raw, true);
        return is_array($array) && is_array($array['data'] ?? null) ? $array['data'] : [];
    }

    /**
     * formatDate
     *
     * @param  mixed $date
     * @return string
     */
    public function formatDate($date): string
    {
        $newDate = date_create((string) $date);
        return $newDate ? date_format($newDate, 'd F Y, h:i:s A') : '';
    }

    /**
     * Usage (MB) into MB/GB/TB
     *
     * @param  mixed $usage
     * @return array
     */
    public static function convertUsage($usage): array
    {
        $usage = is_numeric($usage) ? (float) $usage : 0.0;

        if ($usage < 1000) {
            return ['convert' => number_format($usage, 2, '.', ''), 'unit' => 'MB'];
        }
        if ($usage < 1000000) {
            return ['convert' => number_format($usage / 1000, 2, '.', ''), 'unit' => 'GB'];
        }
        return ['convert' => number_format($usage / 1000000, 2, '.', ''), 'unit' => 'TB'];
    }

    /**
     * Link the repository whose name is the computer name (if enabled)
     *
     * @param  Computer $computer
     * @param  array $repoList
     * @return boolean
     */
    public function automaticLink(Computer $computer, array $repoList): bool
    {
        if (!Config::getConfig()->fields['match']) {
            return false;
        }

        $id = (int) $computer->getID();
        foreach ($repoList as $repo) {
            if (!self::isValidRepo($repo)) {
                continue;
            }
            // Trimming to ignore white spaces
            if (strcmp(trim($computer->fields['name']), trim($repo['name'])) === 0 && $this->linkRepo($repo, $id)) {
                self::logHistory(
                    $id,
                    Log::HISTORY_ADD_RELATION,
                    sprintf(__('%s, automatically linked', 'borgbase'), $repo['id']),
                );
                return true;
            }
        }

        return false;
    }

    /**
     * Display the Borgbase tab of a computer
     *
     * @param  Computer $computer
     * @return boolean
     */
    public static function displayTab(Computer $computer): bool
    {
        if (!Session::haveRight(self::$rightname, READ) || !$computer->can($computer->getID(), READ)) {
            return false;
        }

        $borgbase = new self();
        $computerId = (int) $computer->getID();
        // Actions write the computer's link data and history: they also need the update right on it
        $canEditComputer = $computer->can($computerId, UPDATE);
        $canCreate = $canEditComputer && Session::haveRight(self::$rightname, CREATE);

        $data = [];
        $elements = [];

        $res = $borgbase->checkComputerRepo($computerId);
        if (count($res) === 0 && Config::getConfig()->fields['match']) {
            $borgbase->automaticLink($computer, $borgbase->getRepoByName($computer->fields['name']));
            $res = $borgbase->checkComputerRepo($computerId);
        }

        if (count($res) === 0) {
            if ($canCreate) {
                foreach ($borgbase->getRepoList() as $repo) {
                    if (self::isValidRepo($repo) && count($borgbase->checkLink($repo['id'])) === 0) {
                        $elements[$repo['id']] = $repo['name'];
                    }
                }
            }
            $templatePath = "@borgbase/dropdown.html.twig";
        } else {
            $row = $res[0];

            $row['formatCreatedAt'] = isset($row['createdAt'])
                ? $borgbase->formatDate($row['createdAt'])
                : '';
            $row['formatLastModified'] = isset($row['lastModified'])
                ? $borgbase->formatDate($row['lastModified'])
                : '';
            $row['formatCompactionInterval'] = (isset($row['compactionInterval']) && isset($row['compactionIntervalUnit']))
                ? $row['compactionInterval'] . ' ' . $row['compactionIntervalUnit']
                : '';
            $row['formatCompactionHour'] = (isset($row['compactionHour']) && isset($row['compactionHourTimezone']))
                ? $row['compactionHour'] . ':00 (' . $row['compactionHourTimezone'] . ')'
                : '';

            $usage = self::convertUsage($row['currentUsage'] ?? 0);
            $row['formatCurrentUsage'] = $usage['convert'] . ' ' . $usage['unit'];

            $row['footerDateCreation'] = __('Created on', 'borgbase') . ' ' . $row['date_creation'];
            $row['footerDateMod'] = __('Last update on', 'borgbase') . ' ' . $row['date_mod'];

            $data = $row;
            $templatePath = "@borgbase/repository.html.twig";
        }

        $labels = [
            'notRegistered'         => __('Not registered', 'borgbase'),
            'selectRepo'            => __('Select a repository', 'borgbase'),
            'link'                  => __('Link repository', 'borgbase'),
            'name'                  => __('Name', 'borgbase'),
            'alertDays'             => __('Alert Days', 'borgbase'),
            'version'               => __('Version', 'borgbase'),
            'region'                => __('Region', 'borgbase'),
            'compactionInterval'    => __('Compaction Interval', 'borgbase'),
            'compactionHour'        => __('Compaction Hour', 'borgbase'),
            'encryption'            => __('Encryption', 'borgbase'),
            'usage'                 => __('Current Usage', 'borgbase'),
            'createdAt'             => __('Creation Date', 'borgbase'),
            'lastBackup'            => __('Last Backup', 'borgbase'),
            'confirmDeletion'       => __('Confirm the final deletion?', 'borgbase'),
            'unlink'                => __('Unlink repository', 'borgbase'),
            'reload'                => __('Reload information', 'borgbase'),
        ];

        TemplateRenderer::getInstance()->display($templatePath, [
            'item'      => $borgbase,
            'action'    => self::getFormURL(),
            'elements'  => $elements,
            'data'      => $data,
            'value'     => $computerId,
            'labels'    => $labels,
            'canCreate' => $canCreate,
            'canUpdate' => $canEditComputer && Session::haveRight(self::$rightname, UPDATE),
            'canPurge'  => $canEditComputer && Session::haveRight(self::$rightname, PURGE),
        ]);

        return true;
    }

    /**
     * getAddSearchOptions
     *
     * @param  mixed $itemtype
     * @return array
     */
    public static function getAddSearchOptions($itemtype): array
    {
        $sopt = [];

        if ($itemtype !== 'Computer' || !Session::haveRight(self::$rightname, READ)) {
            return $sopt;
        }

        $joinparams = [
            'beforejoin' => [
                'table'      => Relation::getTable(),
                'joinparams' => [
                    'jointype' => 'itemtype_item',
                ],
            ],
        ];

        $sopt['borgbase'] = ['name' => 'Borgbase'];

        $sopt[self::$sopt] = [
            'table'         => self::getTable(),
            'field'         => 'borg_name',
            'name'          => __('Repository') . ' ' . __('Name'),
            'datatype'      => 'text',
            'forcegroupby'  => true,
            'usehaving'     => true,
            'joinparams'    => $joinparams,
        ];

        $sopt[self::$sopt + 1] = [
            'table'         => self::getTable(),
            'field'         => 'createdAt',
            'name'          => __('Repository') . ' ' . __('Creation Date', 'borgbase'),
            'datatype'      => 'datetime',
            'forcegroupby'  => true,
            'usehaving'     => true,
            'joinparams'    => $joinparams,
        ];

        $sopt[self::$sopt + 2] = [
            'table'         => self::getTable(),
            'field'         => 'lastModified',
            'name'          => __('Repository') . ' ' . __('Last Modification', 'borgbase'),
            'datatype'      => 'datetime',
            'forcegroupby'  => true,
            'usehaving'     => true,
            'joinparams'    => $joinparams,
        ];

        $sopt[self::$sopt + 3] = [
            'table'         => self::getTable(),
            'field'         => 'is_encrypted',
            'name'          => __('Encrypted', 'borgbase'),
            'datatype'      => 'specific',
            'forcegroupby'  => true,
            'usehaving'     => true,
            'searchtype'    => ['equals', 'notequals'],
            'joinparams'    => $joinparams,
        ];

        $sopt[self::$sopt + 4] = [
            'table'         => self::getTable(),
            'field'         => 'currentUsage',
            'name'          => __('Current Usage', 'borgbase'),
            'datatype'      => 'specific',
            'forcegroupby'  => true,
            'usehaving'     => true,
            'nosearch'      => true,
            'joinparams'    => $joinparams,
        ];

        $sopt[self::$sopt + 5] = [
            'table'         => self::getTable(),
            'field'         => 'alertDays',
            'name'          => __('Alert Days', 'borgbase'),
            'datatype'      => 'number',
            'forcegroupby'  => true,
            'usehaving'     => true,
            'joinparams'    => $joinparams,
        ];

        return $sopt;
    }

    /**
     * {@inheritDoc}
     */
    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = []): string
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'is_encrypted':
                return Dropdown::showFromArray(
                    $name,
                    [
                        'encrypted' => __('Encrypted', 'borgbase'),
                    ],
                    ['display' => false, 'value' => $values[$field]],
                );
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    /**
     * {@inheritDoc}
     */
    public static function getSpecificValueToDisplay($field, $values, array $options = []): string
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'is_encrypted':
                return $values[$field] == 'encrypted' ? __('Yes') : __('No');
            case 'currentUsage':
                $usage = self::convertUsage($values[$field]);
                return $usage['convert'] . ' ' . $usage['unit'];
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
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
            $query = "CREATE TABLE `$table` (
                `id`                        INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
                `borg_id`                   VARCHAR(8) NOT NULL,
                `borg_name`                 VARCHAR(255) NOT NULL,
                `computer_id`               INT {$default_key_sign},
                `alertDays`                 INT(11),
                `borg_version`              VARCHAR(255),
                `is_encrypted`              VARCHAR(255),
                `region`                    VARCHAR(255),
                `createdAt`                 VARCHAR(255),
                `lastModified`              VARCHAR(255),
                `compactionInterval`        VARCHAR(255),
                `compactionIntervalUnit`    VARCHAR(255),
                `compactionHour`            VARCHAR(255),
                `compactionHourTimezone`    VARCHAR(255),
                `repoPath`                  VARCHAR(255),
                `currentUsage`              VARCHAR(255) DEFAULT '0',
                `date_creation`             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `date_mod`                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (`id`),
                KEY `computer_id` (`computer_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation}";
            $DB->doQuery($query);
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

        // Search options added to Computer
        $DB->delete('glpi_displaypreferences', [
            'itemtype' => 'Computer',
            'num'      => ['>=', self::$sopt],
            ['num'     => ['<=', self::$sopt + 5]],
        ]);
    }
}
