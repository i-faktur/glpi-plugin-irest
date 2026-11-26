<?php

/**
 * iRest for GLPI: companion plugin of the i-REST application.
 *
 * @copyright 2026 I-Motion SARL
 * @license   GPL-3.0-or-later, see the LICENSE file
 * @link      https://www.i-motion.fr
 */

use Glpi\Plugin\Hooks;
use GlpiPlugin\Irest\Config;
use GlpiPlugin\Irest\HoursBankMenu;
use GlpiPlugin\Irest\Profile;

/** Version of the plugin (SemVer, see CHANGELOG.md). */
define('PLUGIN_IREST_VERSION', '1.2.1');

/** Oldest GLPI version supported. */
define('PLUGIN_IREST_MIN_GLPI_VERSION', '11.0.0');

/** Newest GLPI version supported (included). */
define('PLUGIN_IREST_MAX_GLPI_VERSION', '12.99.99');

/**
 * Declares the hooks of the plugin: configuration tab, rights tab of the profiles, menu entries of the banks of
 * hours (simplified and standard interfaces) for the profiles allowed to see them.
 */
function plugin_init_irest(): void
{
    global $PLUGIN_HOOKS;

    // The key of the API of i-REST is encrypted with the key of GLPI and follows glpi:security:changekey
    $PLUGIN_HOOKS[Hooks::SECURED_CONFIGS]['irest'] = ['api_key'];

    // The profiles of the simplified interface keep in session only the rights listed in Profile::$helpdesk_rights
    // (Profile::cleanProfile()): without it, the right of the plugin is dropped when a customer signs in
    if (!in_array(Profile::RIGHT_HOURS_BANKS, \Profile::$helpdesk_rights, true)) {
        \Profile::$helpdesk_rights[] = Profile::RIGHT_HOURS_BANKS;
    }

    Plugin::registerClass(Config::class, ['addtabon' => \Config::class]);
    Plugin::registerClass(Profile::class, ['addtabon' => \Profile::class]);

    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['irest'] = 'front/config.form.php';
    }
    if (HoursBankMenu::canView()) {
        $PLUGIN_HOOKS[Hooks::HELPDESK_MENU_ENTRY]['irest'] = '/front/hoursbanks.php';
        $PLUGIN_HOOKS[Hooks::HELPDESK_MENU_ENTRY_ICON]['irest'] = HoursBankMenu::getIcon();
        $PLUGIN_HOOKS[Hooks::MENU_TOADD]['irest'] = ['helpdesk' => HoursBankMenu::class];
    }
}

/**
 * Name, version and requirements of the plugin.
 *
 * @return array<string, mixed>
 */
function plugin_version_irest(): array
{
    return [
        'name'         => 'iRest for GLPI',
        'version'      => PLUGIN_IREST_VERSION,
        'author'       => 'I-Motion SARL',
        'license'      => 'GPL-3.0-or-later',
        'homepage'     => 'https://www.i-motion.fr',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_IREST_MIN_GLPI_VERSION,
                'max' => PLUGIN_IREST_MAX_GLPI_VERSION,
            ],
            'php'  => [
                'min' => '8.2',
            ],
        ],
    ];
}

/**
 * Nothing more to check than the requirements given by plugin_version_irest().
 */
function plugin_irest_check_prerequisites(): bool
{
    return true;
}

/**
 * The configuration can be incomplete: the pages of the plugin then say what is missing.
 *
 * @param bool $verbose whether to print why the configuration is not valid
 */
function plugin_irest_check_config($verbose = false): bool
{
    return true;
}
