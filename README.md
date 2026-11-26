# iRest for GLPI

GLPI plugin of I-Motion, companion of the **i-REST** application (billing of GLPI tickets in Dolibarr, creation of
the GLPI entities and users of the Dolibarr customers). It computes nothing: it shows what i-REST computes and
creates what i-REST declares, in one of two modes chosen in its configuration:

- **Read** (by default): the plugin reads the API of i-REST, server to server, with a key issued by i-REST.
- **Push**: i-REST writes its data in GLPI through the REST API of GLPI (object `GlpiPlugin\Irest\Document`) and
  reads back the state of the prerequisites; GLPI never calls i-REST.

- **Banks of hours** (menu of the simplified interface, and *Assistance* in the standard interface): banks of hours
  of the customer of the user (bought, carried over, used, left) and the tasks taken from them, as computed by
  i-REST from the Dolibarr contracts. The customer is the first entity linked in i-REST among the active entity of
  the user and its parents. Shown to the profiles with the right *Banks of hours*, value *My customer* (tab
  *iRest* of the profiles).
- **All customers** (same page, default view of the standard interface for the profiles with the value *All
  customers* of the right): one row by bank of hours of every customer, current banks only or all, sorted by
  customer or by hours left, few or no hours left highlighted; the name of a customer opens its detail. Both values
  of the right are given at installation to the profiles that may update the configuration of GLPI (Super-Admin by
  default); the other profiles are ticked one by one.
- **Preparation** (link of the configuration): what the workflows of i-REST need in this GLPI (e.g. the group of
  the users synced with Dolibarr), with its state; the missing elements are created with the rights of the
  administrator signed in, nothing that exists is changed. The state is sent back to i-REST, which refuses to turn
  on a workflow while a required element is missing.

## Requirements

- GLPI 11.0 to 12.x, PHP 8.2 or later.
- Mode read: i-REST reachable from the GLPI server (server to server: the browsers of the users never call i-REST).
- Mode push: REST API of GLPI enabled for the API user of i-REST, whose profile has the right *Data sent by i-REST*
  (tab *iRest* of the profiles), and the mode push chosen in i-REST on its GLPI connection.

## Versions

Releases: <https://github.com/i-faktur/glpi-plugin-irest/releases> (archive `glpi-irest-X.Y.Z.tar.bz2` and its
SHA-256). The version follows SemVer, independently of the one of i-REST: i-REST tells which version it needs, on the
screen *Companion application* of the connection and in its online help (minimum version by mode, recommended version
and its download), and refuses an older one (calls answered `426` in mode read, nothing but its state written in mode
push).

| iRest for GLPI | i-REST | Modes |
| --- | --- | --- |
| 1.1.0 | 1.0.0 or later | read, push |
| 1.2.0 | 1.0.0 or later | read, push, setup at the installation (manifest exported by i-REST) |
| 1.2.1 (not released yet) | 1.0.0 or later | as 1.2.0, the setup also gives the rights of the GLPI metrics |

Version 1.0.0 was never released. i-REST 1.0.0-rc.1 and 1.0.0-rc.2 have no API for the companion applications.

## Installation

1. Download `glpi-irest-X.Y.Z.tar.bz2` (link on the screen *Companion application* of i-REST, or the releases) and
   extract it in the directory `plugins` of GLPI, which receives the directory `irest` (the name must stay `irest`).
   To update, replace this directory, then *Setup > Plugins*: *Update*.
2. *Setup > Plugins*: install, then enable *iRest for GLPI*. On a GLPI not yet configured for i-REST, this creates the
   profile *i-REST API*, the API user `irest-api` and the API client *i-REST*; page *Preparation*: enable the REST API
   and generate the tokens, shown once, to copy into the i-REST connection. From the command line, as the user of the
   site: `php bin/console plugins:irest:install --enable-api --generate-token --format=json`.
3. Mode read: in i-REST, screen of the GLPI connection, *Companion application*: issue a key; then *Setup >
   General*, tab *iRest*: address of i-REST, key, then *Test the connection to i-REST*.
   Mode push: in i-REST, same screen, choose the mode push; give the profile of the API user of i-REST the right
   *Data sent by i-REST*; tab *iRest*: mode push, then *Check the data written by i-REST*.
4. Profiles that may see the banks of hours: tab *iRest* of the profile, right *Banks of hours* (*My customer*,
   *All customers*).
5. *Preparation*: create the missing elements.

The key is encrypted with the key of GLPI (`glpicrypt.key`) and declared to `glpi:security:changekey`.
Errors of the calls to i-REST are written to `files/_log/irest.log`, never the key.

## Development

- Translations: `locales/*.po` (domain `irest`), compiled with `msgfmt -o locales/<lang>.mo locales/<lang>.po`.
- Mode push: the field `name` of a document is its key (`status`, `prerequisites`, `hours-banks-overview`,
  `hours-banks-<entity>`, written by i-REST; `prerequisites-report` and `companion`, written by the plugin).
- Coding rules: PSR-12, Twig for the views, no raw SQL, rights checked in every script of `front/`.

## License

GPL-3.0-or-later, see `LICENSE`. Copyright (C) 2026 I-Motion SARL.
