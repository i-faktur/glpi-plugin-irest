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
use GlpiPlugin\Irest\Installer;
use GlpiPlugin\Irest\Profile;

/**
 * Installs or updates the plugin: its rights, its configuration and the table of the documents of the mode "push".
 *
 * Each right is created once, for every profile, and given to the profiles that may update the configuration of GLPI
 * (Super-Admin by default): banks of hours (my customer and all the customers), data sent by i-REST (all); the
 * others are ticked one by one. A later installation keeps the choices made on the profiles. On a GLPI not yet
 * configured for i-REST, the base of its installation is then created (Installer); its failure never blocks the
 * installation of the plugin, the page "Preparation" shows what is missing.
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

    try {
        $installer = new Installer();
        if ($installer->mayInstallAutomatically()) {
            $values = \Config::getConfigurationValues(Config::CONTEXT, [Installer::KEY_READ_ONLY]);
            if (!$installer->install(($values[Installer::KEY_READ_ONLY] ?? '') === '1')) {
                Toolbox::logInFile('irest', 'Installation incomplete: ' . implode(' ', $installer->errors) . "\n");
            }
        }
    } catch (\Throwable $exception) {
        Toolbox::logInFile('irest', 'Installation failed: ' . $exception->getMessage() . "\n");
    }

    return true;
}

/**
 * Enables the plugin: its API user again, if the plugin had disabled it.
 */
function plugin_irest_activate(): void
{
    (new Installer())->enableUser();
}

/**
 * Disables the plugin and its API user; nothing is deleted.
 */
function plugin_irest_deactivate(): void
{
    (new Installer())->disableUser();
}

/**
 * Uninstalls the plugin: its rights, its configuration (the key of the API of i-REST included) and its documents. The
 * profile, the API user (disabled) and the API client it created are kept, with the ids that identify them.
 */
function plugin_irest_uninstall(): bool
{
    (new Installer())->disableUser();
    ProfileRight::deleteProfileRights([Profile::RIGHT_HOURS_BANKS, Profile::RIGHT_DOCUMENTS]);
    Config::uninstall();
    Document::uninstall();

    return true;
}
