<?php

/**
 * iRest for GLPI: companion plugin of the i-REST application.
 *
 * @copyright 2026 I-Motion SARL
 * @license   GPL-3.0-or-later, see the LICENSE file
 * @link      https://www.i-motion.fr
 */

namespace GlpiPlugin\Irest;

/**
 * Failure of a call to i-REST, by short code: not_configured, not_pushed, unauthorized, unsupported_version,
 * not_linked, unavailable, invalid_response.
 */
final class ClientException extends \RuntimeException
{
    /**
     * @param string $reason short code of the failure
     */
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }

    /**
     * Message for the user, in the language of GLPI.
     */
    public function userMessage(): string
    {
        return match ($this->reason) {
            'not_configured'      => __('iRest is not configured yet: address of i-REST and key of its API (Setup > General, tab iRest).', 'irest'),
            'not_pushed'          => __('i-REST has not written its data in this GLPI yet (mode push): check the mode of the connection in i-REST and the right of its API user.', 'irest'),
            'unauthorized'        => __('i-REST refuses the key of the configuration: issue a new one in i-REST (screen of the connection, Companion application).', 'irest'),
            'unsupported_version' => __('The version of i-REST is not supported by this version of the plugin.', 'irest'),
            'not_linked'          => __('Your entity is not linked to a customer in i-REST.', 'irest'),
            'unavailable'         => __('i-REST cannot be reached for the moment. Try again later.', 'irest'),
            default               => __('i-REST gave an unexpected answer.', 'irest'),
        };
    }
}
