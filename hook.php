<?php

/**
 * iRest for GLPI: companion plugin of the i-REST application.
 *
 * @copyright 2026 I-Motion SARL
 * @license   GPL-3.0-or-later, see the LICENSE file
 * @link      https://www.i-motion.fr
 */

use GlpiPlugin\Irest\Config;
use GlpiPlugin\Irest\Document;
use GlpiPlugin\Irest\Profile;

/**
 * Installs or updates the plugin: its rights, its configuration and the table of the documents of the mode "push".
 *
 * Each right is created once, for every profile, and given to the profiles that may update the configuration of GLPI
 * (Super-Admin by default): banks of hours (my customer and all the customers), data sent by i-REST (all); the
 * others are ticked one by one. A later installation keeps the choices made on the profiles.
 */
function plugin_irest_install(): bool
{
    global $DB;

    $rights = [Profile::RIGHT_HOURS_BANKS => READ | Profile::ALL_CUSTOMERS, Profile::RIGHT_DOCUMENTS => READ | CREATE | UPDATE | PURGE];
    foreach ($rights as $right => $given) {
        if (countElementsInTable(ProfileRight::getTable(), ['name' => $right]) > 0) {
            continue;
        }
        ProfileRight::addProfileRights([$right]);
        $configRights = $DB->request([
            'SELECT' => ['profiles_id', 'rights'],
            'FROM'   => ProfileRight::getTable(),
            'WHERE'  => ['name' => 'config'],
        ]);
        foreach ($configRights as $row) {
            if (((int) $row['rights'] & UPDATE) === UPDATE) {
                ProfileRight::updateProfileRights((int) $row['profiles_id'], [$right => $given]);
            }
        }
    }
    Config::install();
    Document::install();

    return true;
}

/**
 * Uninstalls the plugin: its rights, its configuration (the key of the API of i-REST included) and its documents.
 */
function plugin_irest_uninstall(): bool
{
    ProfileRight::deleteProfileRights([Profile::RIGHT_HOURS_BANKS, Profile::RIGHT_DOCUMENTS]);
    Config::uninstall();
    Document::uninstall();

    return true;
}
