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
 * Borgbase API
 */
class PluginBorgbaseBorgbase extends CommonDBTM
{
    public static $rightname = 'plugin_borgbase_borgbase';

    public static $itemtype_1 = 'borgbase';

    public static $sopt = 1468;

    public $dohistory = true;


    /**
     * {@inheritDoc}
     */
    public static function getTypeName($nb = 0): string
    {
        return 'Borgbase';
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
        switch ($item::getType()) {
            case 'Computer':
                return self::createTabEntry('Borgbase');
        }
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        switch ($item::getType()) {
            case 'Computer':
                if (Session::haveRight(self::$rightname, READ)) {
                    self::displayTab();
                }
                break;
        }
        return true;
    }

    /**
     * request
     *
     * @param  mixed $query
     * @return string
     */
    public function request($query = ''): string
    {
        if ($query) {
            $config = new PluginBorgbaseConfig();
            $config->getFromDB(1);
            $token = $config->fields['apikey'];

            if ($token) {
                $glpikey = new GLPIKey();

                $header = [
                    'Content-Type: application/json',
                    "Authorization: Bearer " . $glpikey->decrypt($token),
                ];
                $json = '{"query":"' . $query . '"}';
                $ch = curl_init($config->fields['server']);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $data = curl_exec($ch);

                //if (str_contains($data, 'errors')) {
                //    echo '<td><span class="text-muted"><i class="fa-solid fa-xmark"></i> ' . __('Check API connection', 'borgbase') . '</span></td>';
                //}
                return $data;
            }
        }
        return '';
    }

    // Requests
    /**
     * getCommonFields
     *
     * @return string
     */
    private function getRepoCommonFields(): string
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
            'currentUsage'
        ];
        return implode(',', $fields);
    }

    /**
     * getRepo
     *
     * @param  mixed $repoId
     * @return array
     */
    public function getRepo($repoId): array
    {
        $query = '{ repo(repoId:\"' . $repoId . '\") {' . self::getRepoCommonFields() . '}}';
        return $this->getResponseKey($query, 'repo');
    }

    /**
     * getRepoByName
     *
     * @param  string $name
     * @return array
     */
    public function getRepoByName($name): array
    {
        $query = '{ repoList(name:\"' . $name . '\") {' . self::getRepoCommonFields() . '}}';
        return $this->getResponseKey($query, 'repoList');
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
     * @return mixed|array
     */
    private function getResponseKey(string $query, string $key)
    {
        $req = $this->formatRawRequest($this->request($query));
        return $req[$key] ?? [];
    }

    /**
     * checkComputerRepo
     *
     * @param  mixed $id
     * @return array
     */
    public function checkComputerRepo($id): array
    {
        // Check if exists in our database
        /** @var \DBmysql $DB */
        global $DB;

        // Request all
        $iterator = $DB->request([
            'FROM' => $this->getTable(),
            'WHERE' => [
                'computer_id' => $id
            ]
        ]);

        $rows = [];
        foreach ($iterator as $data) {
            array_push($rows, $data);
        }

        return $rows;
    }

    /**
     * checkLink
     *
     * @param  mixed $repoID
     * @return array
     */
    public function checkLink($repoID): array
    {
        // Check if exists relation // computer_id
        /** @var \DBmysql $DB */
        global $DB;

        $iterator = $DB->request([
            'SELECT' => 'computer_id',
            'FROM' => $this->getTable(),
            'WHERE' => [
                'borg_id'       => $repoID,
                'computer_id'   => ['NOT LIKE', '']
            ]
        ]);

        $rows = [];
        foreach ($iterator as $data) {
            array_push($rows, $data);
        }

        return $rows;
    }

    /**
     * linkRepo
     *
     * @param  mixed $repo
     * @param  mixed $id
     * @return boolean
     */
    public function linkRepo($repo, $id)
    {
        global $DB;
        $table = $this->getTable();

        $res = $this->checkLink($repo['id']);
        if (count($res) == 0) {
            // Repository
            $DB->insert(
                $table,
                [
                    'borg_id'                   => $repo['id'],
                    'borg_name'                 => $repo['name'],
                    'computer_id'               => $id,
                    'alertDays'                 => $repo['alertDays'],
                    'borg_version'              => $repo['borgVersion'],
                    'is_encrypted'              => $repo['encryption'],
                    'region'                    => $repo['region'],
                    'createdAt'                 => $repo['createdAt'],
                    'lastModified'              => $repo['lastModified'],
                    'compactionInterval'        => $repo['compactionInterval'],
                    'compactionIntervalUnit'    => $repo['compactionIntervalUnit'],
                    'compactionHour'            => $repo['compactionHour'],
                    'compactionHourTimezone'    => $repo['compactionHourTimezone'],
                    'repoPath'                  => $repo['repoPath'],
                    'currentUsage'              => $repo['currentUsage'],
                ]
            );

            // Relation
            $lastId = $DB->insertId();
            $DB->insert(
                PluginBorgbaseRelation::getTable(),
                [
                    'items_id'  => $id,
                    'itemtype'  => 'Computer',
                    'plugin_borgbase_borgbases_id' => $lastId
                ]
            );

            return true;
        }

        return false;
    }

    /**
     * unlinkRepo
     *
     * @param  mixed $computerId
     * @param  mixed $repoId
     * @return bool
     */
    public function unlinkRepo($computerId, $repoId): bool
    {
        // Check if exists in our database
        global $DB;
        $table = $this->getTable();
        $DB->delete(
            $table,
            [
                'borg_id' => $repoId
            ]
        );

        $DB->delete(
            PluginBorgbaseRelation::getTable(),
            [
                'items_id' => $computerId
            ]
        );

        return true;
    }

    /**
     * reloadRepo
     *
     * @param  mixed $repoId
     * @return boolean
     */
    public function reloadRepo($repoId): bool
    {
        /** @var \DBmysql $DB */
        global $DB;

        $repo = $this->getRepo($repoId);
        if (!empty($repo) && isset($repo['repoPath'])) {
            $DB->update(
                $this->getTable(),
                [
                    'alertDays'                 => $repo['alertDays'],
                    'borg_version'              => $repo['borgVersion'],
                    'is_encrypted'              => $repo['encryption'],
                    'region'                    => $repo['region'],
                    'createdAt'                 => $repo['createdAt'],
                    'lastModified'              => $repo['lastModified'],
                    'compactionInterval'        => $repo['compactionInterval'],
                    'compactionIntervalUnit'    => $repo['compactionIntervalUnit'],
                    'compactionHour'            => $repo['compactionHour'],
                    'compactionHourTimezone'    => $repo['compactionHourTimezone'],
                    'repoPath'                  => $repo['repoPath'],
                    'currentUsage'              => $repo['currentUsage'],
                    'date_mod'                  => date('Y-m-d H:i:s'),
                ],
                [
                    'WHERE' => ['borg_id' => $repoId],
                ]
            );

            return true;
        }

        return false;
    }

    // Formating and display

    /**
     * formatRawRequest
     *
     * @param  mixed $raw
     * @return array
     */
    public function formatRawRequest($raw): array
    {
        $format = [];
        $array = json_decode($raw, true);
        if (!$array) {
            return $format;
        }

        // API returns always {"data":{"x"}} we only want x content
        foreach ($array as $key => $data) {
            if ($key == 'data') {
                if (!empty($data)) {
                    $format = $data;
                }
                break;
            }
        }

        return $format;
    }

    /**
     * formatDate
     *
     * @param  mixed $date
     * @return string
     */
    public function formatDate($date): string
    {
        $format = 'd F Y, h:i:s A';
        $newDate = date_create($date);
        return date_format($newDate, $format);
    }

    /**
     * convertUsage
     *
     * @param  mixed $usage
     * @return array
     */
    public static function convertUsage($usage): array
    {
        $ints = strtok($usage, '.');
        $count = strlen($ints);
        $conversedUsage = 0;
        $unit = '';

        switch ($count) {
            case $count <= 3:
                $conversedUsage = number_format($usage, 2, '.', '');
                $unit = 'MB';
                break;
            case $count <= 6:
                $conversedUsage = number_format($usage / 1000, 2, '.', '');
                $unit = 'GB';
                break;
            case $count <= 9:
                $conversedUsage = number_format($usage / 1000000, 2, '.', '');
                $unit = 'TB';
                break;
        }

        return ['convert' => $conversedUsage, 'unit' => $unit ];
    }

    /**
     * automaticLink
     *
     * @param  Computer $computer
     * @param  array $repoList
     * @return boolean
     */
    public function automaticLink(Computer $computer, array $repoList): bool
    {
        $config = new PluginBorgbaseConfig();
        $config->getFromDB(1);

        $id = $computer->fields['id'];
        // Automatic insert into BD
        foreach ($repoList as $repo) {
            // Trimming to ignore white spaces
            if (strcmp(trim($computer->fields['name']), trim($repo['name'])) == 0) {
                if ($config->fields['match']) {
                    $feedback = $this->linkRepo($repo, $id);
                    if ($feedback) {
                        // Changes
                        $changes[0] = $id;
                        $changes[1] = "";
                        $changes[2] = sprintf(__('%s, ' . __('automatically linked')), $repo['id']);

                        Log::history(
                            $id,
                            'Computer',
                            $changes,
                            'PluginBorgbaseBorgbase',
                            Log::HISTORY_ADD_RELATION
                        );
                        Html::redirect("computer.form.php?id=" . $id);
                        exit;
                    }
                }
            }
        }

        return false;
    }

    /**
     * displayTab
     *
     * @return boolean
     */
    public static function displayTab(): bool
    {
        if (!Session::haveRight(self::$rightname, READ)) {
            return false;
        }

        $borgbase = new self();
        $id = $_GET['id'];
        $computer = new Computer();
        $computer->getFromDB($id);
        $computerId = $computer->fields['id'];
        $computerName = $computer->fields['name'];

        $data = [];
        $elements = [];
        $options = [];

        $res = $borgbase->checkComputerRepo($id);

        if (count($res) == 0) {
            $repoListFounded = $borgbase->getRepoByName($computerName);

            $borgbase->automaticLink($computer, $repoListFounded);

            $repoList = $borgbase->getRepoList();
            foreach ($repoList as $repo) {
                $req = $borgbase->checkLink($repo['id']);
                if (count($req) == 0) {
                    $elements[$repo['id']] = $repo['name'];
                }
            }

            $templatePath = "@borgbase/dropdown.html.twig";
        } else {
            $row = $res[0];

            // Modifications
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

            if (isset($row['currentUsage'])) {
                //Usage into KB/MG/GB
                $usage = $borgbase::convertUsage($row['currentUsage']);
                $row['formatCurrentUsage'] = (isset($usage['convert']) && isset($usage['unit']))
                    ? $usage['convert'] . ' ' . $usage['unit']
                    : '';
            }

            $row['footerDateCreation'] = __('Created on', 'borgbase') . ' ' . $row['date_creation'];
            $row['footerDateMod'] = __('Last update on', 'borgbase') . ' ' . $row['date_mod'];

            $data = $row;
            $templatePath = "@borgbase/repository.html.twig";
        }

        $labels = [
            'notRegistered'         => __('Not registered', 'borgbase'),
            'selectRepo'            => __('Select a repository', 'borgbase'),
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
            'reload'                => __('Reload information', 'borgbase')
        ];

        TemplateRenderer::getInstance()->display($templatePath, [
            'item'      => $borgbase,
            'elements'  => $elements,
            'data'      => $data,
            'value'     => $computerId,
            'labels'    => $labels,
            'params'    => $options,
            'canCreate' => Session::haveRight(self::$rightname, CREATE),
            'canUpdate' => Session::haveRight(self::$rightname, UPDATE),
            'canPurge'  => Session::haveRight(self::$rightname, PURGE)
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

        if ($itemtype == 'Computer' && Session::haveRight(self::$rightname, READ)) {
            $sopt['borgbase'] = ['name' => 'Borgbase'];

            $sopt[self::$sopt] = [
                'table'         => PluginBorgbaseBorgbase::getTable(),
                'field'         => 'borg_name',
                'name'          => __('Repository') . ' ' . __('Name'),
                'datatype'      => 'text',
                'forcegroupby'  => true,
                'usehaving'     => true,
                'joinparams'    => [
                    'beforejoin'    => [
                        'table'         => PluginBorgbaseRelation::getTable(),
                        'joinparams'    => [
                            'jointype'      => 'itemtype_item',
                        ],
                    ],
                ],
            ];

            $sopt[self::$sopt + 1] = [
                'table'         => PluginBorgbaseBorgbase::getTable(),
                'field'         => 'createdAt',
                'name'          => __('Repository') . ' ' . __('Creation Date'),
                'datatype'      => 'datetime',
                'forcegroupby'  => true,
                'usehaving'     => true,
                'joinparams'    => [
                    'beforejoin'    => [
                        'table'         => PluginBorgbaseRelation::getTable(),
                        'joinparams'    => [
                            'jointype'      => 'itemtype_item',
                        ],
                    ],
                ],
            ];

            $sopt[self::$sopt + 2] = [
                'table'         => PluginBorgbaseBorgbase::getTable(),
                'field'         => 'lastModified',
                'name'          => __('Repository') . ' ' . __('Last Modification'),
                'datatype'      => 'datetime',
                'forcegroupby'  => true,
                'usehaving'     => true,
                'joinparams'    => [
                    'beforejoin'    => [
                        'table'         => PluginBorgbaseRelation::getTable(),
                        'joinparams'    => [
                            'jointype'      => 'itemtype_item',
                        ],
                    ],
                ],
            ];

            $sopt[self::$sopt + 3] = [
                'table'         => PluginBorgbaseBorgbase::getTable(),
                'field'         => 'is_encrypted',
                'name'          => __('Encrypted'),
                'datatype'      => 'specific',
                'forcegroupby'  => true,
                'usehaving'     => true,
                'searchtype'    => ['equals', 'notequals'],
                'joinparams'    => [
                    'beforejoin'    => [
                        'table'         => PluginBorgbaseRelation::getTable(),
                        'joinparams'    => [
                            'jointype'      => 'itemtype_item',
                        ],
                    ],
                ],
            ];

            $sopt[self::$sopt + 4] = [
                'table'         => PluginBorgbaseBorgbase::getTable(),
                'field'         => 'currentUsage',
                'name'          => __('Current Usage', 'borgbase'),
                'datatype'      => 'specific',
                'forcegroupby'  => true,
                'usehaving'     => true,
                'nosearch'      => true,
                'joinparams'    => [
                    'beforejoin'    => [
                        'table'         => PluginBorgbaseRelation::getTable(),
                        'joinparams'    => [
                            'jointype'      => 'itemtype_item',
                        ],
                    ],
                ],
            ];

            $sopt[self::$sopt + 5] = [
                'table'         => PluginBorgbaseBorgbase::getTable(),
                'field'         => 'alertDays',
                'name'          => __('Alert Days', 'borgbase'),
                'datatype'      => 'number',
                'forcegroupby'  => true,
                'usehaving'     => true,
                'joinparams'    => [
                    'beforejoin'    => [
                        'table'         => PluginBorgbaseRelation::getTable(),
                        'joinparams'    => [
                            'jointype'      => 'itemtype_item',
                        ],
                    ],
                ],
            ];
        }

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
                        // 'unencrypted' => __('No')
                    ],
                    ['display' => false, 'value' => $values[$field]],
                );
        }
        return parent::getSpecificValueToSelect($field, $values, $options);
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

        $table = (new DbUtils())->getTableForItemtype('PluginBorgbaseBorgbase');
        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");
            $query = "CREATE TABLE `$table` (
                `id`         				INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
                `borg_id` 				    VARCHAR(8) NOT NULL,
                `borg_name`     			VARCHAR(255) NOT NULL,
				`computer_id`				INT {$default_key_sign},
                `alertDays`      			INT(11),
                `borg_version`   			VARCHAR(255),
                `is_encrypted`      		VARCHAR(255),
                `region`      		        VARCHAR(255),
                `createdAt`  				VARCHAR(255),
                `lastModified`  			VARCHAR(255),
                `compactionInterval`  	    VARCHAR(255),
                `compactionIntervalUnit`	VARCHAR(255),
                `compactionHour`  		    VARCHAR(255),
                `compactionHourTimezone`    VARCHAR(255),
                `repoPath`  				VARCHAR(255),
                `currentUsage`  			VARCHAR(255) DEFAULT '0',
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
        //$migration->displayMessage('Uninstalling ' . $table);

        //$DB->queryOrDie("DROP TABLE `$table`", $DB->error());
    }
}
