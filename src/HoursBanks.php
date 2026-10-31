<?php

/**
 * iRest for GLPI: companion plugin of the i-REST application.
 *
 * @copyright 2026 I-Motion SARL
 * @license   GPL-3.0-or-later, see the LICENSE file
 * @link      https://www.i-motion.fr
 */

namespace GlpiPlugin\Irest;

use Entity;
use Html;
use Session;

/**
 * Banks of hours as i-REST computes them: of the customer of the user (the first entity linked in i-REST among the
 * active entity of the user and its parents), of one linked entity, or of all the customers. Only formats, filters
 * and sorts what i-REST gives.
 */
final class HoursBanks
{
    /** Deepest level of entities walked up from the active entity. */
    private const MAX_LEVELS = 30;

    /**
     * @param Client $client client of the API of i-REST
     */
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Customers with their banks of hours and their tasks, ready to show.
     *
     * @return list<array<string, mixed>>
     *
     * @throws ClientException
     */
    public function forActiveEntity(): array
    {
        return self::customers($this->client->hoursBanks(self::entitiesOf((int) Session::getActiveEntity())));
    }

    /**
     * Customer of one entity linked in i-REST, with its banks of hours and its tasks (view "All customers").
     *
     * @return list<array<string, mixed>>
     *
     * @throws ClientException "not_linked" when the entity is not linked to a customer
     */
    public function forEntity(int $entityId): array
    {
        return self::customers($this->client->hoursBanks([$entityId]));
    }

    /**
     * Banks of hours of all the customers, one row by bank (a customer whose contracts cannot be read gives one row
     * with "error"), and whether some contracts could not be read.
     *
     * @param bool   $currentOnly keep the banks neither closed nor ended
     * @param string $sort        "name" (order of i-REST) or "balance" (hours left, fewest first)
     *
     * @return array{rows: list<array<string, mixed>>, incomplete: bool}
     *
     * @throws ClientException
     */
    public function overview(bool $currentOnly, string $sort): array
    {
        $answer = $this->client->hoursBanksOverview();
        $today = date('Y-m-d');
        $rows = [];
        foreach ((array) ($answer['customers'] ?? []) as $customer) {
            $row = ['name' => (string) $customer['name'], 'entity' => (int) $customer['entityId'], 'error' => $customer['error'] !== null];
            if ($row['error']) {
                $rows[] = $row + ['bank' => null, 'left' => INF];
                continue;
            }
            foreach ((array) $customer['banks'] as $bank) {
                if ($currentOnly && ($bank['line']['status'] === 'closed' || ($bank['end'] !== null && $bank['end'] < $today))) {
                    continue;
                }
                $rows[] = $row + ['bank' => self::bank($bank), 'left' => (float) $bank['balance']];
            }
        }
        if ($sort === 'balance') {
            usort($rows, static fn (array $first, array $second): int => $first['left'] <=> $second['left']);
        }

        return ['rows' => $rows, 'incomplete' => ($answer['error'] ?? null) !== null];
    }

    /**
     * Customers of an answer of i-REST with their banks of hours and their tasks, ready to show.
     *
     * @param array<string, mixed> $answer
     *
     * @return list<array<string, mixed>>
     */
    private static function customers(array $answer): array
    {
        $customers = [];
        foreach ((array) ($answer['customers'] ?? []) as $customer) {
            $customers[] = [
                'name'    => (string) $customer['name'],
                'error'   => $customer['error'] ?? null,
                'banks'   => array_map(self::bank(...), (array) $customer['banks']),
                'tickets' => array_map(self::ticket(...), (array) $customer['tickets']),
            ];
        }

        return $customers;
    }

    /**
     * Entity and its parents, nearest first.
     *
     * @return list<int>
     */
    private static function entitiesOf(int $entityId): array
    {
        $entities = [];
        $entity = new Entity();
        while (count($entities) < self::MAX_LEVELS && $entity->getFromDB($entityId)) {
            $entities[] = $entityId;
            if ($entity->fields['entities_id'] === null || (int) $entity->fields['entities_id'] === $entityId) {
                break;
            }
            $entityId = (int) $entity->fields['entities_id'];
        }

        return $entities;
    }

    /**
     * Bank of hours formatted for the page.
     *
     * @param array<string, mixed> $bank
     *
     * @return array<string, string|bool>
     */
    private static function bank(array $bank): array
    {
        return [
            'contract'        => (string) $bank['contract']['ref'],
            'start'           => $bank['start'] !== null ? (string) Html::convDate($bank['start']) : '…',
            'end'             => $bank['end'] !== null ? (string) Html::convDate($bank['end']) : '…',
            'bought'          => self::hours($bank['bought']),
            'carried'         => self::hours($bank['carried']),
            'taken'           => self::hours($bank['taken']),
            'balance'         => self::hours($bank['balance']),
            'level'           => (string) ($bank['level'] ?? 'ok'),
            'closed'          => $bank['line']['status'] === 'closed',
            'rollover'        => match ($bank['rollover']) {
                'carry' => __('Carried over to the next period', 'irest'),
                'lose'  => __('Lost at the end of the period', 'irest'),
                default => '',
            },
        ];
    }

    /**
     * Task taken from a bank of hours formatted for the page.
     *
     * @param array<string, mixed> $ticket
     *
     * @return array<string, string>
     */
    private static function ticket(array $ticket): array
    {
        return [
            'ticket'  => (string) $ticket['ticket'],
            'label'   => (string) $ticket['label'],
            'hours'   => self::hours($ticket['hours']),
            'date'    => (string) Html::convDate($ticket['billedAt']),
            'invoice' => (string) ($ticket['invoice'] ?? ''),
        ];
    }

    /**
     * Hours in the number format of the user.
     */
    private static function hours(mixed $hours): string
    {
        return (string) Html::formatNumber((float) $hours);
    }
}
