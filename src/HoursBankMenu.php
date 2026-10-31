<?php

/**
 * iRest for GLPI: companion plugin of the i-REST application.
 *
 * @copyright 2026 I-Motion SARL
 * @license   GPL-3.0-or-later, see the LICENSE file
 * @link      https://www.i-motion.fr
 */

namespace GlpiPlugin\Irest;

use CommonGLPI;
use Session;

/**
 * Entry "Banks of hours" of the menu "Assistance" of the standard interface (the simplified interface gets it
 * through the hook helpdesk_menu_entry), for the profiles with the right of the plugin.
 */
class HoursBankMenu extends CommonGLPI
{
    // $rightname is not redeclared (typed in GLPI 12, not in GLPI 11, and a static property assigned without
    // redeclaration would change it for every class): the right of the plugin is checked by canView()

    /**
     * Whether the user may see banks of hours: those of the customer of the entity or those of all the customers.
     */
    public static function canView(): bool
    {
        return Session::haveRightsOr(Profile::RIGHT_HOURS_BANKS, [READ, Profile::ALL_CUSTOMERS]);
    }

    /**
     * Name of the entry.
     */
    public static function getTypeName($nb = 0)
    {
        return __('Banks of hours', 'irest');
    }

    /**
     * Name of the entry in the menu.
     */
    public static function getMenuName()
    {
        return self::getTypeName();
    }

    /**
     * Icon of the plugin (Tabler).
     */
    public static function getIcon()
    {
        return 'ti ti-hourglass';
    }

    /**
     * Entry of the menu, for the profiles allowed to see the banks of hours.
     *
     * @return array<string, mixed>|false
     */
    public static function getMenuContent()
    {
        if (!self::canView()) {
            return false;
        }

        return [
            'title' => self::getMenuName(),
            'page'  => '/plugins/irest/front/hoursbanks.php',
            'icon'  => self::getIcon(),
        ];
    }
}
