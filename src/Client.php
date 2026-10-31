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
 * Client of the API of i-REST (server to server, key of the configuration). The plugin computes nothing: what it
 * shows and what it creates comes from here. Errors are thrown as ClientException with a short code; their detail
 * goes to the log of the plugin (files/_log/irest.log), never the key.
 */
final class Client
{
    /** Version of the API of i-REST this plugin speaks. */
    public const API_VERSION = 1;

    /**
     * @param string $url     address of i-REST, without trailing slash
     * @param string $apiKey  key of the API of i-REST
     * @param int    $timeout time-out of a call, in seconds
     */
    public function __construct(
        private readonly string $url,
        private readonly string $apiKey,
        private readonly int $timeout,
    ) {
    }

    /**
     * Client of the configuration of the plugin.
     *
     * @throws ClientException "not_configured" when the address or the key is missing
     */
    public static function fromConfig(): self
    {
        $values = Config::values();
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
}
