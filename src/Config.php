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
use Glpi\Application\View\TemplateRenderer;
use GLPIKey;
use Session;

/**
 * Configuration of the plugin (context "plugin:irest" of glpi_configs): mode ("read": the plugin calls the API of
 * i-REST; "push": i-REST writes its data in GLPI, see Document), address of i-REST, key of the API of i-REST
 * (encrypted with the key of GLPI, never shown again) and time-out of the calls. Shown as the tab "iRest" of
 * Setup > General.
 */
class Config extends \Config
{
    /** Context of the configuration in glpi_configs. */
    public const CONTEXT = 'plugin:irest';

    /** Time-out of a call to i-REST by default, in seconds. */
    public const DEFAULT_TIMEOUT = 10;

    /** Mode "read": the plugin calls the API of i-REST (by default). */
    public const MODE_READ = 'read';

    /** Mode "push": i-REST writes its data in GLPI, the plugin never calls i-REST. */
    public const MODE_PUSH = 'push';

    /**
     * Name of the tab of Setup > General, for the administrators.
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof \Config && Session::haveRight('config', UPDATE)) {
            return self::createTabEntry('iRest', 0, null, HoursBankMenu::getIcon());
        }

        return '';
    }

    /**
     * Shows the form of the configuration.
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!$item instanceof \Config || !Session::haveRight('config', UPDATE)) {
            return false;
        }
        $values = self::values();
        TemplateRenderer::getInstance()->display('@irest/config.html.twig', [
            'mode'       => $values['mode'],
            'url'        => $values['url'],
            'has_key'    => $values['api_key'] !== '',
            'timeout'    => $values['timeout'],
            'last_write' => $values['mode'] === self::MODE_PUSH ? Document::lastWrite() : null,
            'action'     => self::pageUrl('front/config.form.php'),
        ]);

        return true;
    }

    /**
     * Address of a page of the plugin, from the root of GLPI.
     */
    public static function pageUrl(string $page): string
    {
        global $CFG_GLPI;

        return $CFG_GLPI['root_doc'] . '/plugins/irest/' . ltrim($page, '/');
    }

    /**
     * Current configuration, the key decrypted ('' when not set).
     *
     * @return array{mode: string, url: string, api_key: string, timeout: int}
     */
    public static function values(): array
    {
        $stored = \Config::getConfigurationValues(self::CONTEXT, ['mode', 'url', 'api_key', 'timeout']);
        $key = (string) ($stored['api_key'] ?? '');

        return [
            'mode'    => ($stored['mode'] ?? '') === self::MODE_PUSH ? self::MODE_PUSH : self::MODE_READ,
            'url'     => rtrim((string) ($stored['url'] ?? ''), '/'),
            'api_key' => $key !== '' ? (string) (new GLPIKey())->decrypt($key) : '',
            'timeout' => max(1, (int) ($stored['timeout'] ?? self::DEFAULT_TIMEOUT)),
        ];
    }

    /**
     * Saves the mode of the plugin.
     *
     * @throws \InvalidArgumentException when the mode is not one
     */
    public static function saveMode(string $mode): void
    {
        if (!in_array($mode, [self::MODE_READ, self::MODE_PUSH], true)) {
            throw new \InvalidArgumentException(__('Unknown mode.', 'irest'));
        }
        \Config::setConfigurationValues(self::CONTEXT, ['mode' => $mode]);
    }

    /**
     * Saves the configuration; a blank key keeps the current one.
     *
     * @throws \InvalidArgumentException when the address or the time-out is not valid
     */
    public static function save(string $url, string $apiKey, int $timeout): void
    {
        $url = rtrim(trim($url), '/');
        if ($url !== '' && !preg_match('#^https?://[^\s/]+#', $url)) {
            throw new \InvalidArgumentException(__('The address of i-REST must start with http:// or https://.', 'irest'));
        }
        if ($timeout < 1 || $timeout > 120) {
            throw new \InvalidArgumentException(__('The time-out is a number of seconds between 1 and 120.', 'irest'));
        }
        $values = ['url' => $url, 'timeout' => $timeout];
        if (trim($apiKey) !== '') {
            // GLPI encrypts the key itself since the plugin declares it in the hook secured_configs; encrypting
            // it here as well would store it encrypted twice
            $glpiKey = new GLPIKey();
            $values['api_key'] = $glpiKey->isConfigSecured(self::CONTEXT, 'api_key') ? trim($apiKey) : $glpiKey->encrypt(trim($apiKey));
        }
        \Config::setConfigurationValues(self::CONTEXT, $values);
    }

    /**
     * Creates the configuration of the plugin with its default values.
     */
    public static function install(): void
    {
        // Only the missing values are written: writing the stored key again would encrypt it once more
        $current = \Config::getConfigurationValues(self::CONTEXT);
        $missing = array_diff_key(['mode' => self::MODE_READ, 'url' => '', 'api_key' => '', 'timeout' => self::DEFAULT_TIMEOUT], $current);
        if ($missing !== []) {
            \Config::setConfigurationValues(self::CONTEXT, $missing);
        }
    }

    /**
     * Removes the configuration of the plugin.
     */
    public static function uninstall(): void
    {
        (new \Config())->deleteByCriteria(['context' => self::CONTEXT]);
    }
}
