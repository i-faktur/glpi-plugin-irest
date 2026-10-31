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

// Saves the configuration of the plugin (tab "iRest" of Setup > General) and tests it
// (GLPI runs the legacy scripts inside a function: the globals must be declared)
global $CFG_GLPI;

Session::checkRight('config', UPDATE);

if (isset($_POST['save']) || isset($_POST['test'])) {
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
        Session::addMessageAfterRedirect(sprintf(__('i-REST answers: connection "%s".', 'irest'), (string) ($status['connection']['label'] ?? '?')), false, INFO);
    } catch (ClientException $exception) {
        Session::addMessageAfterRedirect($exception->userMessage(), false, ERROR);
    }
}

// The configuration is a tab of Setup > General
Html::redirect($CFG_GLPI['root_doc'] . '/front/config.form.php?forcetab=' . urlencode(Config::class . '$0'));
