<?php

/**
 * iRest for GLPI: companion plugin of the i-REST application.
 *
 * @copyright 2026 I-Motion SARL
 * @license   GPL-3.0-or-later, see the LICENSE file
 * @link      https://www.i-motion.fr
 */

namespace GlpiPlugin\Irest;

use APIClient;
use GLPIKey;
use Profile_User;
use ProfileRight;
use User;

/**
 * Creates what i-REST needs before it can connect to this GLPI, from the manifest produced by i-REST
 * (install/manifest.json): a profile with its rights, an API user assigned to it on the root entity, recursively, and
 * an API client. The global settings of the API are only changed on demand, and the tokens are only generated on
 * demand and shown once.
 *
 * Only the elements created here are ever changed, and only to add what is missing: they are recognised by the ids
 * kept in the configuration, so an element of the same name made by someone else is reported and left alone.
 */
final class Installer
{
    /** Configuration: id of the profile created by the plugin. */
    public const KEY_PROFILE = 'install_profile_id';

    /** Configuration: id of the API user created by the plugin. */
    public const KEY_USER = 'install_user_id';

    /** Configuration: id of the API client created by the plugin. */
    public const KEY_CLIENT = 'install_client_id';

    /** Configuration: "1" while the installation is limited to the read rights. */
    public const KEY_READ_ONLY = 'install_read_only';

    /** Configuration: "1" when the plugin itself disabled the API user, so that only then it enables it again. */
    public const KEY_DISABLED = 'install_user_disabled';

    /** Every key of the installation, kept when the plugin is uninstalled so that a new installation finds its elements. */
    public const KEYS = [self::KEY_PROFILE, self::KEY_USER, self::KEY_CLIENT, self::KEY_READ_ONLY, self::KEY_DISABLED];

    /** Format of the manifest this version reads. */
    private const MANIFEST_SCHEMA = 1;

    /** Root entity, where the API user gets its profile. */
    private const ROOT_ENTITY = 0;

    /** @var list<string> errors of the last installation, for the administrator */
    public array $errors = [];

    /**
     * Manifest delivered with the plugin, null when it is missing or of another format.
     *
     * @return array{schema: int, application: string, names: array<string, string>, rights: list<array{key: string, bits: int, write: bool}>}|null
     */
    public function manifest(): ?array
    {
        $path = dirname(__DIR__) . '/install/manifest.json';
        $content = is_readable($path) ? file_get_contents($path) : false;
        $manifest = $content !== false ? json_decode($content, true) : null;
        if (!is_array($manifest) || (int) ($manifest['schema'] ?? 0) !== self::MANIFEST_SCHEMA || ($manifest['application'] ?? '') !== 'glpi') {
            return null;
        }

        return $manifest;
    }

    /**
     * Whether the plugin already created elements.
     */
    public function isManaged(): bool
    {
        return $this->id(self::KEY_PROFILE) > 0 || $this->id(self::KEY_USER) > 0 || $this->id(self::KEY_CLIENT) > 0;
    }

    /**
     * Whether the installation may create the base by itself: on a first installation, or to complete its own
     * elements. A GLPI already configured for i-REST has its own API user, so no second one is created there.
     */
    public function mayInstallAutomatically(): bool
    {
        if ($this->isManaged()) {
            return true;
        }
        $values = \Config::getConfigurationValues(Config::CONTEXT, ['mode', 'url', 'api_key']);

        return ($values['url'] ?? '') === '' && ($values['api_key'] ?? '') === '' && ($values['mode'] ?? Config::MODE_READ) !== Config::MODE_PUSH;
    }

    /**
     * State of each element of the base, null without manifest.
     *
     * @return list<array{code: string, label: string, status: string}>|null status: present, missing or foreign
     */
    public function states(): ?array
    {
        $manifest = $this->manifest();
        if ($manifest === null) {
            return null;
        }
        $names = $manifest['names'];
        $profileId = $this->id(self::KEY_PROFILE);
        $userId = $this->id(self::KEY_USER);
        $clientId = $this->id(self::KEY_CLIENT);
        $core = \Config::getConfigurationValues('core', ['enable_api', 'enable_api_login_external_token']);
        $user = new User();
        $hasUser = $userId > 0 && $user->getFromDB($userId);
        $client = new APIClient();
        $hasClient = $clientId > 0 && $client->getFromDB($clientId);

        $states = [
            ['code' => 'api', 'label' => __('REST API of GLPI enabled', 'irest'), 'status' => !empty($core['enable_api']) ? 'present' : 'missing'],
            ['code' => 'api.token', 'label' => __('API login with a user token enabled', 'irest'), 'status' => !empty($core['enable_api_login_external_token']) ? 'present' : 'missing'],
            ['code' => 'profile', 'label' => sprintf(__('Profile "%s"', 'irest'), $names['profile']), 'status' => $this->elementState($profileId, new \Profile(), $names['profile'])],
            ['code' => 'user', 'label' => sprintf(__('API user "%s"', 'irest'), $names['user']), 'status' => $this->elementState($userId, new User(), $names['user'])],
            ['code' => 'assignment', 'label' => __('Profile of the API user on the root entity, recursive', 'irest'), 'status' => $hasUser && $profileId > 0 && $this->hasAssignment($userId, $profileId) ? 'present' : 'missing'],
            ['code' => 'default_profile', 'label' => __('Default profile of the API user', 'irest'), 'status' => $hasUser && $profileId > 0 && (int) $user->fields['profiles_id'] === $profileId ? 'present' : 'missing'],
        ];
        $current = $profileId > 0 ? ProfileRight::getProfileRights($profileId) : [];
        foreach ($this->expectedRights($manifest) as $key => $bits) {
            $states[] = ['code' => 'right.' . $key, 'label' => sprintf(__('Right %s', 'irest'), $key), 'status' => (((int) ($current[$key] ?? 0)) & $bits) === $bits ? 'present' : 'missing'];
        }
        $states[] = ['code' => 'client', 'label' => sprintf(__('API client "%s"', 'irest'), $names['client']), 'status' => $this->elementState($clientId, new APIClient(), $names['client'])];
        $states[] = ['code' => 'tokens', 'label' => __('Application token and user token', 'irest'), 'status' => $hasUser && $hasClient && !empty($user->fields['api_token']) && !empty($client->fields['app_token']) ? 'present' : 'missing'];

        return $states;
    }

    /**
     * Creates or completes the profile, the API user, its assignment and the API client. Never removes a right.
     *
     * @param bool $readOnly whether to give the read rights only (trial without writing)
     *
     * @return bool whether everything could be done (details in $errors)
     */
    public function install(bool $readOnly = false): bool
    {
        $this->errors = [];
        $manifest = $this->manifest();
        if ($manifest === null) {
            $this->errors[] = __('The installation manifest is missing or unreadable: install the plugin again.', 'irest');

            return false;
        }
        // Read by User and APIClient, and not set yet from the command line
        $_SESSION['glpi_currenttime'] ??= date('Y-m-d H:i:s');
        \Config::setConfigurationValues(Config::CONTEXT, [self::KEY_READ_ONLY => $readOnly ? '1' : '0']);

        $profileId = $this->ensureProfile($manifest['names']['profile']);
        if ($profileId > 0) {
            $this->completeRights($profileId, $this->expectedRights($manifest));
            $userId = $this->ensureUser($manifest['names']['user'], $profileId);
            if ($userId > 0) {
                $this->completeUser($userId, $profileId);
            }
        }
        $this->ensureClient($manifest['names']['client']);

        return $this->errors === [];
    }

    /**
     * Enables the REST API of GLPI and the login with a user token: global settings, only on demand.
     */
    public function enableApi(): void
    {
        \Config::setConfigurationValues('core', ['enable_api' => 1, 'enable_api_login_external_token' => 1]);
    }

    /**
     * Generates a new application token and a new user token; the previous ones stop working.
     *
     * @return array{app_token: string, user_token: string}|null tokens to show once, null on error
     */
    public function generateTokens(): ?array
    {
        $_SESSION['glpi_currenttime'] ??= date('Y-m-d H:i:s');
        $user = new User();
        $client = new APIClient();
        if (!$user->getFromDB($this->id(self::KEY_USER)) || !$client->getFromDB($this->id(self::KEY_CLIENT))) {
            return null;
        }
        $previous = (string) $user->fields['api_token'];
        // GLPI ignores a new token when the user signed in has fewer rights than the API user
        if (!$user->update(['id' => $user->getID(), '_reset_api_token' => 1]) || !$client->update(['id' => $client->getID(), '_reset_app_token' => 1])) {
            return null;
        }
        $user->getFromDB($user->getID());
        $client->getFromDB($client->getID());
        if (empty($user->fields['api_token']) || (string) $user->fields['api_token'] === $previous || empty($client->fields['app_token'])) {
            return null;
        }
        $key = new GLPIKey();

        return ['app_token' => (string) $key->decrypt($client->fields['app_token']), 'user_token' => (string) $key->decrypt($user->fields['api_token'])];
    }

    /**
     * Disables the API user created by the plugin, when the plugin is turned off. Nothing is deleted.
     */
    public function disableUser(): void
    {
        $user = new User();
        if ($this->id(self::KEY_USER) <= 0 || !$user->getFromDB($this->id(self::KEY_USER)) || empty($user->fields['is_active'])) {
            return;
        }
        $user->update(['id' => $user->getID(), 'is_active' => 0]);
        $user->getFromDB($user->getID());
        if (empty($user->fields['is_active'])) {
            \Config::setConfigurationValues(Config::CONTEXT, [self::KEY_DISABLED => '1']);
        }
    }

    /**
     * Enables the API user again when the plugin had disabled it; one disabled by an administrator stays so.
     */
    public function enableUser(): void
    {
        $values = \Config::getConfigurationValues(Config::CONTEXT, [self::KEY_DISABLED]);
        $user = new User();
        if (($values[self::KEY_DISABLED] ?? '') !== '1' || !$user->getFromDB($this->id(self::KEY_USER))) {
            return;
        }
        $user->update(['id' => $user->getID(), 'is_active' => 1]);
        \Config::setConfigurationValues(Config::CONTEXT, [self::KEY_DISABLED => '0']);
    }

    /**
     * Id kept in the configuration, 0 if none.
     */
    private function id(string $key): int
    {
        return (int) (\Config::getConfigurationValues(Config::CONTEXT, [$key])[$key] ?? 0);
    }

    /**
     * State of an element from the id kept by the plugin and the existence of an element of that name.
     */
    private function elementState(int $ownId, \CommonDBTM $item, string $name): string
    {
        if ($ownId > 0) {
            return 'present';
        }

        return $item->getFromDBByCrit(['name' => $name]) ? 'foreign' : 'missing';
    }

    /**
     * Rights to give, bits by right: all of them, or the read rights only while the installation is limited to them.
     *
     * @param array{rights: list<array{key: string, bits: int, write: bool}>} $manifest
     *
     * @return array<string, int>
     */
    private function expectedRights(array $manifest): array
    {
        $values = \Config::getConfigurationValues(Config::CONTEXT, [self::KEY_READ_ONLY]);
        $readOnly = ($values[self::KEY_READ_ONLY] ?? '') === '1';
        $rights = [];
        foreach ($manifest['rights'] as $right) {
            if (!$readOnly || !$right['write']) {
                $rights[$right['key']] = ($rights[$right['key']] ?? 0) | (int) $right['bits'];
            }
        }

        return $rights;
    }

    /**
     * Id of the profile of the plugin, created if needed; 0 on error or when a profile of that name is not ours.
     */
    private function ensureProfile(string $name): int
    {
        $id = $this->id(self::KEY_PROFILE);
        if ($id > 0) {
            return $id;
        }
        if ((new \Profile())->getFromDBByCrit(['name' => $name])) {
            $this->errors[] = sprintf(__('A profile named "%s" already exists: it is not changed.', 'irest'), $name);

            return 0;
        }
        $id = (int) (new \Profile())->add(['name' => $name, 'interface' => 'central']);
        if ($id <= 0) {
            $this->errors[] = sprintf(__('The profile "%s" could not be created.', 'irest'), $name);

            return 0;
        }
        \Config::setConfigurationValues(Config::CONTEXT, [self::KEY_PROFILE => (string) $id]);

        return $id;
    }

    /**
     * Adds the missing bits to the rights of the profile, keeping those it already has.
     *
     * @param array<string, int> $rights bits by right
     */
    private function completeRights(int $profileId, array $rights): void
    {
        $current = ProfileRight::getProfileRights($profileId, array_keys($rights));
        $update = [];
        foreach ($rights as $key => $bits) {
            $value = (int) ($current[$key] ?? 0);
            if (($value & $bits) !== $bits) {
                $update[$key] = $value | $bits;
            }
        }
        if ($update !== []) {
            ProfileRight::updateProfileRights($profileId, $update);
        }
    }

    /**
     * Id of the API user of the plugin, created if needed, without a password (it signs in with its token); 0 on error
     * or when a user of that login is not ours.
     */
    private function ensureUser(string $login, int $profileId): int
    {
        $id = $this->id(self::KEY_USER);
        if ($id > 0) {
            return $id;
        }
        if ((new User())->getFromDBByCrit(['name' => $login])) {
            $this->errors[] = sprintf(__('A user named "%s" already exists: it is not changed.', 'irest'), $login);

            return 0;
        }
        $id = (int) (new User())->add(['name' => $login, 'realname' => 'i-REST API', 'is_active' => 1, '_entities_id' => self::ROOT_ENTITY, '_is_recursive' => 1]);
        if ($id <= 0) {
            $this->errors[] = sprintf(__('The user "%s" could not be created.', 'irest'), $login);

            return 0;
        }
        // GLPI gave the new user its default profile: the API user must hold the profile of i-REST only
        (new Profile_User())->deleteByCriteria(['users_id' => $id, 'NOT' => ['profiles_id' => $profileId]], true);
        \Config::setConfigurationValues(Config::CONTEXT, [self::KEY_USER => (string) $id]);

        return $id;
    }

    /**
     * Gives the API user its profile on the root entity, recursively, as its default profile.
     */
    private function completeUser(int $userId, int $profileId): void
    {
        if (!$this->hasAssignment($userId, $profileId)
            && !(new Profile_User())->add(['users_id' => $userId, 'profiles_id' => $profileId, 'entities_id' => self::ROOT_ENTITY, 'is_recursive' => 1, 'is_dynamic' => 0])) {
            $this->errors[] = __('The profile could not be given to the API user.', 'irest');

            return;
        }
        $user = new User();
        if ($user->getFromDB($userId) && (int) $user->fields['profiles_id'] !== $profileId) {
            $user->update(['id' => $userId, 'profiles_id' => $profileId, 'entities_id' => self::ROOT_ENTITY]);
        }
    }

    /**
     * Whether the user holds the profile on the root entity, recursively.
     */
    private function hasAssignment(int $userId, int $profileId): bool
    {
        return countElementsInTable(Profile_User::getTable(), ['users_id' => $userId, 'profiles_id' => $profileId, 'entities_id' => self::ROOT_ENTITY, 'is_recursive' => 1]) > 0;
    }

    /**
     * Creates the API client of the plugin if needed, without a token until one is asked for.
     */
    private function ensureClient(string $name): void
    {
        if ($this->id(self::KEY_CLIENT) > 0) {
            return;
        }
        if ((new APIClient())->getFromDBByCrit(['name' => $name])) {
            $this->errors[] = sprintf(__('An API client named "%s" already exists: it is not changed.', 'irest'), $name);

            return;
        }
        $id = (int) (new APIClient())->add(['name' => $name, 'is_active' => 1]);
        if ($id <= 0) {
            $this->errors[] = sprintf(__('The API client "%s" could not be created.', 'irest'), $name);

            return;
        }
        \Config::setConfigurationValues(Config::CONTEXT, [self::KEY_CLIENT => (string) $id]);
    }
}
