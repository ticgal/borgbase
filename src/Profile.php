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
use Html;
use Migration;
use ProfileRight;
use Session;

/**
 * Profile tab (rights of the plugin)
 */
class Profile extends CommonGLPI
{
    public static string $rightname = 'profile';

    /**
     * {@inheritDoc}
     */
    public static function getTypeName($nb = 0): string
    {
        return "Borgbase";
    }

    /**
     * {@inheritDoc}
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if ($item instanceof \Profile && $item->getField('id') && Session::haveRight(self::$rightname, READ)) {
            return self::createTabEntry(self::getTypeName());
        }
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if ($item instanceof \Profile && $item->getField('id')) {
            return self::showForProfile((int) $item->getID());
        }

        return true;
    }

    /**
     * No list page for this itemtype (/plugins/borgbase/front/profile.php would
     * otherwise be served by the core generic list controller)
     *
     * @return bool
     */
    public static function canView(): bool
    {
        return false;
    }

    /**
     * getAllRights
     *
     * @param  mixed $all
     * @return array
     */
    public static function getAllRights($all = false): array
    {
        return [
            [
                'itemtype'  => Borgbase::class,
                'label'     => Borgbase::getTypeName(),
                'field'     => Borgbase::$rightname,
            ],
        ];
    }

    /**
     * showForProfile
     *
     * @param  int $profiles_id
     * @return boolean
     */
    public static function showForProfile($profiles_id = 0): bool
    {
        $profile = new \Profile();
        if (!$profile->getFromDB($profiles_id)) {
            return false;
        }
        $canedit = self::canUpdate();

        echo "<div class='firstbloc'>";
        if ($canedit) {
            echo "<form method='post' action='" . htmlescape(\Profile::getFormURL()) . "'>";
        }

        $profile->displayRightsChoiceMatrix(self::getAllRights(), [
            'canedit' => $canedit,
            'title'   => self::getTypeName(),
        ]);

        if ($canedit) {
            echo "<div class='center'>";
            echo Html::hidden('id', ['value' => $profiles_id]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo "</div>";
            Html::closeForm();
        }
        echo "</div>";

        return true;
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
        $migration->displayMessage('Installing profile rights');
        foreach (self::getAllRights() as $right) {
            if (countElementsInTable('glpi_profilerights', ['name' => $right['field']]) === 0) {
                ProfileRight::addProfileRights([$right['field']]);
            }
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
        $migration->displayMessage('Remove profile rights');
        foreach (self::getAllRights() as $right) {
            ProfileRight::deleteProfileRights([$right['field']]);
        }
    }
}
