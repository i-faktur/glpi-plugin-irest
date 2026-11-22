<?php

/**
 * iRest for GLPI: companion plugin of the i-REST application.
 *
 * @copyright 2026 I-Motion SARL
 * @license   GPL-3.0-or-later, see the LICENSE file
 * @link      https://www.i-motion.fr
 */

namespace GlpiPlugin\Irest;

use CommonDBTM;
use DBConnection;
use Session;

/**
 * Document of the mode "push" (table glpi_plugin_irest_documents): JSON written by i-REST through the REST API of
 * GLPI (status, prerequisites, banks of hours of all the customers in the root entity, of each customer in its
 * entity, recursive), read by the pages of the plugin instead of calling i-REST; and the documents the plugin writes
 * for i-REST (report of the prerequisites, name and version of the plugin). The field "name" is the key of the
 * document (letters, digits and "-"). Written by the API user of i-REST only, with the right "Data sent by i-REST".
 */
class Document extends CommonDBTM
{
    /** Version and connection, written by i-REST. */
    public const STATUS = 'status';

    /** Prerequisites of the workflows, written by i-REST. */
    public const PREREQUISITES = 'prerequisites';

    /** Banks of hours of all the customers, written by i-REST. */
    public const HOURS_BANKS_OVERVIEW = 'hours-banks-overview';

    /** Start of the key of the banks of hours of a customer, followed by the id of its entity. */
    public const HOURS_BANKS_PREFIX = 'hours-banks-';

    /** State of the prerequisites, written by the plugin and read back by i-REST. */
    public const PREREQUISITES_REPORT = 'prerequisites-report';

    /** Name and version of the plugin, written by the plugin for i-REST. */
    public const COMPANION = 'companion';

    // $rightname is inherited, never redeclared (typed in GLPI 12, not in GLPI 11): the static can*() methods
    // check the right of the plugin instead

    /**
     * Name of the object.
     */
    public static function getTypeName($nb = 0)
    {
        return __('Data sent by i-REST', 'irest');
    }

    /**
     * Read through the API by the user of i-REST.
     */
    public static function canView(): bool
    {
        return Session::haveRight(Profile::RIGHT_DOCUMENTS, READ);
    }

    /**
     * Created through the API by the user of i-REST.
     */
    public static function canCreate(): bool
    {
        return Session::haveRight(Profile::RIGHT_DOCUMENTS, CREATE);
    }

    /**
     * Updated through the API by the user of i-REST.
     */
    public static function canUpdate(): bool
    {
        return Session::haveRight(Profile::RIGHT_DOCUMENTS, UPDATE);
    }

    /**
     * Purged through the API by the user of i-REST.
     */
    public static function canPurge(): bool
    {
        return Session::haveRight(Profile::RIGHT_DOCUMENTS, PURGE);
    }

    /**
     * No trash: a document is replaced or purged.
     */
    public static function canDelete(): bool
    {
        return false;
    }

    /**
     * Refuses a key that is not one.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>|false
     */
    public function prepareInputForAdd($input)
    {
        return self::isValidKey((string) ($input['name'] ?? '')) ? $input : false;
    }

    /**
     * Refuses a key that is not one.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>|false
     */
    public function prepareInputForUpdate($input)
    {
        return !isset($input['name']) || self::isValidKey((string) $input['name']) ? $input : false;
    }

    /**
     * Whether a text has the form of a key of a document.
     */
    public static function isValidKey(string $key): bool
    {
        return preg_match('/^[a-z0-9-]{1,64}$/', $key) === 1;
    }

    /**
     * Content of a document read in the database, whatever the entities of the session (the pages of the plugin
     * choose what they show), null when absent.
     *
     * @return array{content: string, date: string|null}|null content (JSON text) and date of the last write
     */
    public static function read(string $key): ?array
    {
        global $DB;

        foreach ($DB->request(['SELECT' => ['content', 'date_mod'], 'FROM' => self::getTable(), 'WHERE' => ['name' => $key], 'LIMIT' => 1]) as $row) {
            return ['content' => (string) $row['content'], 'date' => $row['date_mod'] !== null ? (string) $row['date_mod'] : null];
        }

        return null;
    }

    /**
     * Writes a document of the plugin for i-REST (root entity, recursive), created or replaced.
     */
    public static function write(string $key, string $content): bool
    {
        global $DB;

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        if (self::read($key) === null) {
            return (bool) $DB->insert(self::getTable(), ['name' => $key, 'content' => $content, 'entities_id' => 0, 'is_recursive' => 1, 'date_creation' => $now, 'date_mod' => $now]);
        }

        return (bool) $DB->update(self::getTable(), ['content' => $content, 'date_mod' => $now], ['name' => $key]);
    }

    /**
     * Date of the last document written by i-REST (those of the plugin left out), null when it wrote nothing.
     */
    public static function lastWrite(): ?string
    {
        global $DB;

        foreach ($DB->request(['SELECT' => ['MAX' => 'date_mod AS last_write'], 'FROM' => self::getTable(), 'WHERE' => ['NOT' => ['name' => [self::PREREQUISITES_REPORT, self::COMPANION]]]]) as $row) {
            return $row['last_write'] !== null ? (string) $row['last_write'] : null;
        }

        return null;
    }

    /**
     * Creates the table of the documents if needed and writes the name and version of the plugin for i-REST.
     */
    public static function install(): void
    {
        global $DB;

        $table = self::getTable();
        if (!$DB->tableExists($table)) {
            $charset = DBConnection::getDefaultCharset();
            $collation = DBConnection::getDefaultCollation();
            $sign = DBConnection::getDefaultPrimaryKeySignOption();
            $DB->doQuery(
                "CREATE TABLE `$table` (
                    `id` int $sign NOT NULL AUTO_INCREMENT,
                    `name` varchar(64) NOT NULL DEFAULT '',
                    `content` longtext,
                    `entities_id` int $sign NOT NULL DEFAULT '0',
                    `is_recursive` tinyint NOT NULL DEFAULT '0',
                    `date_creation` timestamp NULL DEFAULT NULL,
                    `date_mod` timestamp NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `name` (`name`),
                    KEY `entities_id` (`entities_id`),
                    KEY `is_recursive` (`is_recursive`),
                    KEY `date_creation` (`date_creation`),
                    KEY `date_mod` (`date_mod`)
                ) ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE=$collation ROW_FORMAT=DYNAMIC"
            );
        }
        self::write(self::COMPANION, (string) json_encode(['name' => 'irest-glpi', 'version' => PLUGIN_IREST_VERSION]));
    }

    /**
     * Drops the table of the documents.
     */
    public static function uninstall(): void
    {
        global $DB;

        $DB->dropTable(self::getTable(), true);
    }
}
