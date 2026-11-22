<?php

/**
 * iRest for GLPI: companion plugin of the i-REST application.
 *
 * @copyright 2026 I-Motion SARL
 * @license   GPL-3.0-or-later, see the LICENSE file
 * @link      https://www.i-motion.fr
 */

namespace GlpiPlugin\Irest;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Toolbox;

/**
 * Data of i-REST for the pages of the plugin, in the mode of the configuration. Mode "read": calls to the API of
 * i-REST, server to server, with the key of the configuration. Mode "push": the same answers, read in the documents
 * i-REST wrote in GLPI (Document), and the report of the prerequisites written there for i-REST: GLPI never calls
 * i-REST. The plugin computes nothing: what it shows and what it creates comes from here. Errors are thrown as
 * ClientException with a short code; their detail goes to the log of the plugin (files/_log/irest.log), never the
 * key.
 */
final class Client
{
    /** Version of the API of i-REST this plugin speaks. */
    public const API_VERSION = 1;

    /**
     * @param string $url     address of i-REST, without trailing slash
     * @param string $apiKey  key of the API of i-REST
     * @param int    $timeout time-out of a call, in seconds
     * @param bool   $push    whether the data are read in the documents written by i-REST (mode "push")
     */
    public function __construct(
        private readonly string $url,
        private readonly string $apiKey,
        private readonly int $timeout,
        private readonly bool $push = false,
    ) {
    }

    /**
     * Client of the configuration of the plugin.
     *
     * @throws ClientException "not_configured" when the address or the key is missing in mode "read"
     */
    public static function fromConfig(): self
    {
        $values = Config::values();
        if ($values['mode'] === Config::MODE_PUSH) {
            return new self('', '', $values['timeout'], true);
        }
        if ($values['url'] === '' || $values['api_key'] === '') {
            throw new ClientException('not_configured');
        }

        return new self($values['url'], $values['api_key'], $values['timeout']);
    }

    /**
     * Version of the API and connection of the key.
     *
     * @return array<string, mixed>
     *
     * @throws ClientException
     */
    public function status(): array
    {
        $status = $this->call('GET', 'status');
        if (($status['api'] ?? null) !== self::API_VERSION) {
            throw new ClientException('unsupported_version');
        }

        return $status;
    }

    /**
     * Banks of hours of the customer of the first linked entity of the list.
     *
     * @param list<int> $entities entity of the user and its parents, nearest first
     *
     * @return array<string, mixed>
     *
     * @throws ClientException "not_linked" when none of the entities is linked to a customer
     */
    public function hoursBanks(array $entities): array
    {
        return $this->call('GET', 'hours-banks', ['entities' => implode(',', $entities)]);
    }

    /**
     * Banks of hours of all the customers, without the tasks.
     *
     * @return array<string, mixed>
     *
     * @throws ClientException
     */
    public function hoursBanksOverview(): array
    {
        return $this->call('GET', 'hours-banks/overview');
    }

    /**
     * Prerequisites of the workflows in this GLPI.
     *
     * @return list<array<string, mixed>>
     *
     * @throws ClientException
     */
    public function prerequisites(): array
    {
        $answer = $this->call('GET', 'prerequisites');

        return is_array($answer['prerequisites'] ?? null) ? array_values($answer['prerequisites']) : [];
    }

    /**
     * Reports the state of the prerequisites checked in this GLPI.
     *
     * @param array<string, array{status: string, detail: string|null}> $states state by code
     *
     * @throws ClientException
     */
    public function report(array $states): void
    {
        $this->call('POST', 'prerequisites/report', [], ['prerequisites' => $states === [] ? new \stdClass() : $states]);
    }

    /**
     * Calls the API and decodes its JSON answer.
     *
     * @param array<string, string> $query
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     *
     * @throws ClientException "unauthorized", "not_linked", "unavailable" or "invalid_response"
     */
    private function call(string $method, string $path, array $query = [], ?array $body = null): array
    {
        if ($this->push) {
            return $this->local($method, $path, $query, $body);
        }
        $options = [
            'timeout'     => $this->timeout,
            'http_errors' => false,
            'headers'     => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept'        => 'application/json',
                'X-Irest-Client' => 'irest-glpi/' . PLUGIN_IREST_VERSION,
            ],
            'query'       => $query,
        ];
        if ($body !== null) {
            $options['json'] = $body;
        }
        try {
            $response = (new HttpClient())->request($method, $this->url . '/api/v1/' . $path, $options);
        } catch (GuzzleException $exception) {
            Toolbox::logInFile('irest', sprintf('%s %s: %s' . PHP_EOL, $method, $path, $exception->getMessage()));
            throw new ClientException('unavailable');
        }
        $code = $response->getStatusCode();
        $answer = json_decode((string) $response->getBody(), true);
        if ($code === 401) {
            throw new ClientException('unauthorized');
        }
        if ($code === 404 && is_array($answer) && ($answer['error'] ?? null) === 'not_linked') {
            throw new ClientException('not_linked');
        }
        if ($code !== 200 || !is_array($answer)) {
            Toolbox::logInFile('irest', sprintf('%s %s: HTTP %d' . PHP_EOL, $method, $path, $code));
            throw new ClientException($code >= 500 ? 'unavailable' : 'invalid_response');
        }

        return $answer;
    }

    /**
     * Answer of a call read in the documents written by i-REST (mode "push"); the report of the prerequisites is
     * written as a document that i-REST reads back. The banks of hours of a customer are those of the first entity
     * of the list that has a document, as i-REST answers.
     *
     * @param array<string, string>     $query
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     *
     * @throws ClientException "not_pushed", "not_linked" or "invalid_response"
     */
    private function local(string $method, string $path, array $query, ?array $body): array
    {
        if ($method === 'POST' && $path === 'prerequisites/report') {
            if (!Document::write(Document::PREREQUISITES_REPORT, (string) json_encode($body))) {
                throw new ClientException('invalid_response');
            }

            return ['recorded' => count((array) ($body['prerequisites'] ?? []))];
        }
        if ($path === 'hours-banks') {
            foreach (array_filter(explode(',', $query['entities'] ?? ''), 'ctype_digit') as $entityId) {
                $document = Document::read(Document::HOURS_BANKS_PREFIX . $entityId);
                if ($document !== null) {
                    return self::decoded($document['content']);
                }
            }
            // i-REST writes one document per linked entity: none means not linked (or nothing written yet)
            throw new ClientException(Document::lastWrite() !== null ? 'not_linked' : 'not_pushed');
        }
        $keys = ['status' => Document::STATUS, 'prerequisites' => Document::PREREQUISITES, 'hours-banks/overview' => Document::HOURS_BANKS_OVERVIEW];
        $document = isset($keys[$path]) ? Document::read($keys[$path]) : null;
        if ($document === null) {
            throw new ClientException('not_pushed');
        }

        return self::decoded($document['content']);
    }

    /**
     * Content of a document decoded.
     *
     * @return array<string, mixed>
     *
     * @throws ClientException "invalid_response" when it is not a JSON object
     */
    private static function decoded(string $content): array
    {
        $answer = json_decode($content, true);
        if (!is_array($answer)) {
            throw new ClientException('invalid_response');
        }

        return $answer;
    }
}
