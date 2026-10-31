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
use GlpiPlugin\Irest\HoursBankMenu;
use GlpiPlugin\Irest\HoursBanks;
use GlpiPlugin\Irest\Profile;

// Page "Banks of hours": banks of hours of the customer of the user and the tasks taken from them; with the right
// "All customers", the banks of all the customers (default view of the standard interface) and the detail of one
// GLPI runs the legacy scripts inside a function: the globals must be declared
global $CFG_GLPI;

Session::checkRightsOr(Profile::RIGHT_HOURS_BANKS, [READ, Profile::ALL_CUSTOMERS]);
$allCustomers = Session::haveRight(Profile::RIGHT_HOURS_BANKS, Profile::ALL_CUSTOMERS);
$ownCustomer = Session::haveRight(Profile::RIGHT_HOURS_BANKS, READ);

$entityId = $allCustomers && isset($_GET['entity']) ? (int) $_GET['entity'] : null;
if ($entityId !== null) {
    $view = 'entity';
} elseif ($allCustomers && (!$ownCustomer || (Session::getCurrentInterface() !== 'helpdesk' && ($_GET['view'] ?? '') !== 'mine'))) {
    $view = 'all';
} else {
    $view = 'mine';
}
$currentOnly = ($_GET['current'] ?? '1') === '1';
$sort = ($_GET['sort'] ?? '') === 'balance' ? 'balance' : 'name';

$customers = [];
$overview = ['rows' => [], 'incomplete' => false];
$error = null;
try {
    $hoursBanks = new HoursBanks(Client::fromConfig());
    if ($view === 'all') {
        $overview = $hoursBanks->overview($currentOnly, $sort);
    } else {
        $customers = $view === 'entity' ? $hoursBanks->forEntity($entityId) : $hoursBanks->forActiveEntity();
    }
} catch (ClientException $exception) {
    $error = $exception->userMessage();
}

if (Session::getCurrentInterface() === 'helpdesk') {
    Html::helpHeader(HoursBankMenu::getTypeName());
} else {
    Html::header(HoursBankMenu::getTypeName(), '', 'helpdesk', HoursBankMenu::class);
}
$page = Config::pageUrl('front/hoursbanks.php');
if ($view === 'all') {
    TemplateRenderer::getInstance()->display('@irest/hoursbanks_all.html.twig', [
        'rows'         => $overview['rows'],
        'incomplete'   => $overview['incomplete'],
        'error'        => $error,
        'page'         => $page,
        'own_customer' => $ownCustomer,
        'current_only' => $currentOnly,
        'sort'         => $sort,
    ]);
} else {
    TemplateRenderer::getInstance()->display('@irest/hoursbanks.html.twig', [
        'customers'    => $customers,
        'error'        => $error,
        'ticket_url'   => $CFG_GLPI['root_doc'] . '/front/ticket.form.php?id=',
        'all_url'      => $allCustomers ? $page : null,
    ]);
}
if (Session::getCurrentInterface() === 'helpdesk') {
    Html::helpFooter();
} else {
    Html::footer();
}
