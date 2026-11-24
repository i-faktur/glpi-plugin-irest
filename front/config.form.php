<?php

/**
 * iRest for GLPI: companion plugin of the i-REST application.
 *
 * @copyright 2026 I-Motion SARL
 * @license   GPL-3.0-or-later, see the LICENSE file
 * @link      https://www.i-motion.fr
 */

use GlpiPlugin\Irest\Client;
use GlpiPlugin\Irest\ClientException;
use GlpiPlugin\Irest\Config;
use GlpiPlugin\Irest\Prerequisites;

// Saves the configuration of the plugin (tab "iRest" of Setup > General) and tests it
// (GLPI runs the legacy scripts inside a function: the globals must be declared)
global $CFG_GLPI;

Session::checkRight('config', UPDATE);

if (isset($_POST['save_mode'])) {
    try {
        Config::saveMode((string) ($_POST['mode'] ?? ''));
        Session::addMessageAfterRedirect(__('The configuration is saved.', 'irest'));
    } catch (InvalidArgumentException $exception) {
        Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
    }
} elseif (isset($_POST['save']) || isset($_POST['test'])) {
    if (isset($_POST['save'])) {
        try {
            Config::save((string) ($_POST['url'] ?? ''), (string) ($_POST['api_key'] ?? ''), (int) ($_POST['timeout'] ?? Config::DEFAULT_TIMEOUT));
            Session::addMessageAfterRedirect(__('The configuration is saved.', 'irest'));
        } catch (InvalidArgumentException $exception) {
            Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
        }
    }
    try {
        $status = Client::fromConfig()->status();
        $message = Config::values()['mode'] === Config::MODE_PUSH ? __('Data of i-REST found: connection "%s".', 'irest') : __('i-REST answers: connection "%s".', 'irest');
        Session::addMessageAfterRedirect(sprintf($message, (string) ($status['connection']['label'] ?? '?')), false, INFO);
        // The configuration works: what the workflows need is created without a click (nothing that exists is changed)
        if (isset($_POST['save']) || Config::values()['mode'] === Config::MODE_PUSH) {
            $created = (new Prerequisites(Client::fromConfig()))->createMissing();
            if ($created !== []) {
                Session::addMessageAfterRedirect(sprintf(__('Created: %s', 'irest'), implode(', ', $created)), false, INFO);
            }
        }
    } catch (ClientException $exception) {
        Session::addMessageAfterRedirect($exception->userMessage(), false, ERROR);
    }
}

// The configuration is a tab of Setup > General
Html::redirect($CFG_GLPI['root_doc'] . '/front/config.form.php?forcetab=' . urlencode(Config::class . '$0'));
