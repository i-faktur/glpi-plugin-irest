<?php

/**
 * iRest for GLPI: companion plugin of the i-REST application.
 *
 * @copyright 2026 I-Motion SARL
 * @license   GPL-3.0-or-later, see the LICENSE file
 * @link      https://www.i-motion.fr
 */

use GlpiPlugin\Irest\Config;
use GlpiPlugin\Irest\Profile;

/**
 * Installs the plugin: its right and its configuration.
 *
 * The right is created at the first installation only, for every profile, and given (my customer and all the
 * customers) to the profiles that may update the configuration of GLPI (Super-Admin by default); the others are
 * ticked one by one. A later installation keeps the choices made on the profiles.
 */
function plugin_irest_install(): bool
{
    global $DB;

    if (countElementsInTable(ProfileRight::getTable(), ['name' => Profile::RIGHT_HOURS_BANKS]) === 0) {
        ProfileRight::addProfileRights([Profile::RIGHT_HOURS_BANKS]);
        $configRights = $DB->request([
            'SELECT' => ['profiles_id', 'rights'],
            'FROM'   => ProfileRight::getTable(),
            'WHERE'  => ['name' => 'config'],
        ]);
        foreach ($configRights as $row) {
            if (((int) $row['rights'] & UPDATE) === UPDATE) {
                ProfileRight::updateProfileRights((int) $row['profiles_id'], [Profile::RIGHT_HOURS_BANKS => READ | Profile::ALL_CUSTOMERS]);
            }
        }
    }
    Config::install();

    return true;
}

/**
 * Uninstalls the plugin: its right and its configuration (the key of the API of i-REST included).
 */
function plugin_irest_uninstall(): bool
{
    ProfileRight::deleteProfileRights([Profile::RIGHT_HOURS_BANKS]);
    Config::uninstall();

    return true;
}
