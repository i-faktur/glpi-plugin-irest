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
use GlpiPlugin\Irest\Prerequisites;

// Page "Preparation": prerequisites of the workflows of i-REST in this GLPI, creation of those missing
Session::checkRight('config', UPDATE);

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
    'states' => array_map(static fn (array $state): array => $state + ['comment' => Prerequisites::text($state['prerequisite']['spec']['comment'] ?? '')], $states),
    'error'  => $error,
    'action' => Config::pageUrl('front/prerequisites.php'),
]);
Html::footer();
