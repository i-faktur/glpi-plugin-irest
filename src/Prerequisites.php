<?php

/**
 * iRest for GLPI: companion plugin of the i-REST application.
 *
 * @copyright 2026 I-Motion SARL
 * @license   GPL-3.0-or-later, see the LICENSE file
 * @link      https://www.i-motion.fr
 */

namespace GlpiPlugin\Irest;

use Group;
use Session;

/**
 * Preparation of GLPI: checks the prerequisites declared by i-REST for its workflows and creates those missing,
 * with the rights of the administrator signed in. This class only knows how to check and create a kind of element
 * (a group); which elements, with which names, comes from i-REST. An element that exists is never changed.
 */
final class Prerequisites
{
    /** Kind of element this plugin checks and creates. */
    public const KIND_GROUP = 'group';

    /**
     * @param Client $client client of the API of i-REST
     */
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Prerequisites declared by i-REST with their state in this GLPI, reported back to i-REST.
     *
     * @return list<array{prerequisite: array<string, mixed>, status: string, detail: string|null, label: string}>
     *
     * @throws ClientException
     */
    public function check(): array
    {
        $states = [];
        $report = [];
        foreach ($this->client->prerequisites() as $prerequisite) {
            $state = $this->stateOf($prerequisite);
            if ($state === null) {
                continue;
            }
            $states[] = ['prerequisite' => $prerequisite] + $state;
            $report[(string) $prerequisite['code']] = ['status' => $state['status'], 'detail' => $state['detail']];
        }
        $this->client->report($report);

        return $states;
    }

    /**
     * Creates the missing prerequisites among those chosen, with the choices of the administrator.
     *
     * @param list<string>                         $codes   codes of the prerequisites to create
     * @param array<string, array<string, string>> $choices choices of the administrator, by code
     *
     * @return list<string> labels of the elements created
     *
     * @throws ClientException
     */
    public function create(array $codes, array $choices): array
    {
        $created = [];
        foreach ($this->client->prerequisites() as $prerequisite) {
            $code = (string) $prerequisite['code'];
            if (!in_array($code, $codes, true) || ($this->stateOf($prerequisite)['status'] ?? null) !== 'missing') {
                continue;
            }
            if ($prerequisite['kind'] === self::KIND_GROUP && $this->createGroup($prerequisite['spec'], $choices[$code] ?? [])) {
                $created[] = (string) $prerequisite['spec']['name'];
            }
        }

        return $created;
    }

    /**
     * Creates every missing prerequisite with the default choices of i-REST, then reports the new state to i-REST.
     *
     * @return list<string> labels of the elements created
     *
     * @throws ClientException
     */
    public function createMissing(): array
    {
        $codes = [];
        foreach ($this->check() as $state) {
            if ($state['status'] === 'missing') {
                $codes[] = (string) $state['prerequisite']['code'];
            }
        }
        if ($codes === []) {
            return [];
        }
        $created = $this->create($codes, []);
        $this->check();

        return $created;
    }

    /**
     * State of a prerequisite in this GLPI, null for a kind this plugin does not handle.
     *
     * @param array<string, mixed> $prerequisite
     *
     * @return array{status: string, detail: string|null, label: string}|null
     */
    private function stateOf(array $prerequisite): ?array
    {
        if (($prerequisite['kind'] ?? null) !== self::KIND_GROUP) {
            return null;
        }
        $spec = $prerequisite['spec'];
        $label = sprintf(__('Group "%s"', 'irest'), $spec['name']);
        $group = new Group();
        if (!$group->getFromDBByCrit(['name' => (string) $spec['name']])) {
            return ['status' => 'missing', 'detail' => null, 'label' => $label];
        }
        if ((string) $group->fields['entities_id'] !== (string) $spec['entity']) {
            return ['status' => 'different', 'detail' => sprintf(__('In the entity #%s', 'irest'), $group->fields['entities_id']), 'label' => $label];
        }

        return ['status' => 'present', 'detail' => null, 'label' => $label];
    }

    /**
     * Creates a group of users.
     *
     * @param array<string, mixed>  $spec    name, entity, recursive, comment ({fr, en, de})
     * @param array<string, string> $choices "recursive": "1" or "0"
     */
    private function createGroup(array $spec, array $choices): bool
    {
        $recursive = isset($choices['recursive']) ? $choices['recursive'] === '1' : (bool) $spec['recursive'];

        return (new Group())->add([
            'name'         => (string) $spec['name'],
            'entities_id'  => (int) $spec['entity'],
            'is_recursive' => $recursive ? 1 : 0,
            'comment'      => self::text($spec['comment'] ?? []),
        ]) !== false;
    }

    /**
     * Text in the language of the user from texts by language code (fr, en, de), English otherwise.
     *
     * @param array<string, string>|string $texts
     */
    public static function text(array|string $texts): string
    {
        if (is_string($texts)) {
            return $texts;
        }
        $language = substr((string) ($_SESSION['glpilanguage'] ?? 'en_GB'), 0, 2);

        return (string) ($texts[$language] ?? $texts['en'] ?? reset($texts) ?: '');
    }

    /**
     * Whether the user may prepare GLPI (right to update the configuration).
     */
    public static function canPrepare(): bool
    {
        return Session::haveRight('config', UPDATE);
    }
}
