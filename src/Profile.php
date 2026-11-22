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
use Html;
use Session;

/**
 * Tab "iRest" of the profiles: right to see the banks of hours of one's customer (READ) or of all the customers
 * (ALL_CUSTOMERS), both given at installation to the profiles that may update the configuration of GLPI (Super-Admin
 * by default; the others are ticked one by one); right to the data sent by i-REST in mode "push" (Document), for the
 * profile of the API user of i-REST. The preparation of GLPI and the configuration need the right to update the
 * configuration of GLPI.
 */
class Profile extends \Profile
{
    /** Right to see the banks of hours: READ for the customer of one's entity, ALL_CUSTOMERS for all of them. */
    public const RIGHT_HOURS_BANKS = 'plugin_irest_hoursbanks';

    /** Value of the right to see the banks of hours of all the customers (view "All customers"). */
    public const ALL_CUSTOMERS = 1024;

    /** Right to read and write the documents of the mode "push" (Document), for the API user of i-REST only. */
    public const RIGHT_DOCUMENTS = 'plugin_irest_documents';

    // $rightname is inherited ('profile'), never redeclared: it is typed in GLPI 12 and not in GLPI 11, so no
    // redeclaration suits both

    /**
     * Name of the tab of a profile.
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof \Profile && $item->getField('id')) {
            return self::createTabEntry('iRest', 0, null, HoursBankMenu::getIcon());
        }

        return '';
    }

    /**
     * Shows the rights of the plugin for the profile, saved by the form of the profiles.
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!$item instanceof \Profile) {
            return false;
        }
        $profile = new \Profile();
        $profile->getFromDB($item->getID());
        $canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);
        echo "<form method='post' action='" . htmlescape($profile->getFormURL()) . "'>";
        $profile->displayRightsChoiceMatrix(self::getAllRights(), [
            'canedit'       => $canedit,
            'default_class' => 'tab_bg_2',
            'title'         => 'iRest',
        ]);
        if ($canedit) {
            echo "<div class='center'>";
            echo Html::hidden('id', ['value' => $item->getID()]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
            echo '</div>';
        }
        Html::closeForm();

        return true;
    }

    /**
     * Rights of the plugin.
     *
     * @return list<array<string, mixed>>
     */
    public static function getAllRights($all = false): array
    {
        return [
            [
                'itemtype' => HoursBankMenu::class,
                'label'    => __('Banks of hours', 'irest'),
                'field'    => self::RIGHT_HOURS_BANKS,
                'rights'   => [READ => __('My customer', 'irest'), self::ALL_CUSTOMERS => __('All customers', 'irest')],
            ],
            [
                'itemtype' => Document::class,
                'label'    => __('Data sent by i-REST (API user of i-REST)', 'irest'),
                'field'    => self::RIGHT_DOCUMENTS,
                'rights'   => [READ => __('Read'), CREATE => __('Create'), UPDATE => __('Update'), PURGE => _x('button', 'Delete permanently')],
            ],
        ];
    }
}
