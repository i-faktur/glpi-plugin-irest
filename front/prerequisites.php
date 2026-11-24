<?php

/**
 * iRest for GLPI: companion plugin of the i-REST application.
 *
 * @copyright 2026 I-Motion SARL
 * @license   GPL-3.0-or-later, see the LICENSE file
 * @link      https://www.i-motion.fr
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Irest\Client;
use GlpiPlugin\Irest\ClientException;
use GlpiPlugin\Irest\Config;
use GlpiPlugin\Irest\Installer;
use GlpiPlugin\Irest\Prerequisites;

// Page "Preparation": base of the installation of i-REST and prerequisites of its workflows, creation of those missing
Session::checkRight('config', UPDATE);

$installer = new Installer();
$tokens = null;
if (isset($_POST['install']) || isset($_POST['install_read_only'])) {
    if ($installer->install(isset($_POST['install_read_only']))) {
        Session::addMessageAfterRedirect(__('The installation is up to date.', 'irest'), false, INFO);
    } else {
        Session::addMessageAfterRedirect(implode(' ', $installer->errors), false, ERROR);
    }
    Html::redirect(Config::pageUrl('front/prerequisites.php'));
} elseif (isset($_POST['enable_api'])) {
    $installer->enableApi();
    Session::addMessageAfterRedirect(__('The REST API of GLPI and the login with a user token are enabled.', 'irest'), false, INFO);
    Html::redirect(Config::pageUrl('front/prerequisites.php'));
} elseif (isset($_POST['generate_tokens'])) {
    // Shown in this answer only, without a redirection: the tokens are never displayed again
    $tokens = $installer->generateTokens();
    if ($tokens === null) {
        Session::addMessageAfterRedirect(__('The tokens could not be generated.', 'irest'), false, ERROR);
    } else {
        header('Cache-Control: no-store');
    }
}

$states = [];
$error = null;
try {
    $prerequisites = new Prerequisites(Client::fromConfig());
    if (isset($_POST['create'])) {
        $codes = array_values(array_filter((array) ($_POST['codes'] ?? []), 'is_string'));
        $choices = [];
        foreach ((array) ($_POST['choices'] ?? []) as $code => $values) {
            $choices[(string) $code] = array_map('strval', array_filter((array) $values, 'is_scalar'));
        }
        $created = $prerequisites->create($codes, $choices);
        Session::addMessageAfterRedirect(
            $created === [] ? __('Nothing was created.', 'irest') : sprintf(__('Created: %s', 'irest'), implode(', ', $created)),
            false,
            $created === [] ? WARNING : INFO
        );
        Html::redirect(Config::pageUrl('front/prerequisites.php'));
    }
    $states = $prerequisites->check();
} catch (ClientException $exception) {
    $error = $exception->userMessage();
}

Html::header(__('iRest preparation', 'irest'), '', 'config', 'config');
TemplateRenderer::getInstance()->display('@irest/prerequisites.html.twig', [
    'install_states' => $installer->states(),
    'install_managed' => $installer->isManaged(),
    'install_automatic' => $installer->mayInstallAutomatically(),
    'tokens' => $tokens,
    'states' => array_map(static fn (array $state): array => $state + ['comment' => Prerequisites::text($state['prerequisite']['spec']['comment'] ?? '')], $states),
    'error'  => $error,
    'action' => Config::pageUrl('front/prerequisites.php'),
]);
Html::footer();
