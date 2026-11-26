# Changelog

All notable changes of iRest for GLPI. Versions follow [Semantic Versioning](https://semver.org/).

## [1.2.1] - Unreleased

### Fixed
- The automatic setup gives the rights of the GLPI metrics workflow of i-REST (`glpi_metrics`): ITIL categories and
  notification queue, read only. The manifest (`install/manifest.json`) is regenerated from i-REST, which now lists
  the rights of every type of workflow. The update completes the profile created by the plugin; a profile made by
  hand is left alone, as before.

## [1.2.0] - 2026-11-24

### Added
- Automatic setup, on a GLPI not yet configured for i-REST: profile "i-REST API" with its rights, API user `irest-api`
  on the root entity, recursively, as its default profile, and API client "i-REST", from a manifest produced by i-REST
  (`install/manifest.json`). Only the elements created by the plugin are completed; an element of the same name is
  reported and left alone.
- Section "Installation" of the page "Preparation": state of each element, creation (read and write, or read-only for
  a trial), button to enable the REST API, tokens generated on demand and shown once.
- Command `plugins:irest:install` for the packages (`--read-only`, `--enable-api`, `--generate-token`, `--irest-url`,
  `--irest-key`, `--format=json`).

### Changed
- The prerequisites of the workflows are created without a click once the configuration is saved with a working key.
- Disabling or uninstalling the plugin disables its API user (nothing is deleted); enabling it again restores that
  user.

## [1.1.0] - 2026-11-23

First published version.

### Added
- Page "Banks of hours" for the simplified and standard interfaces: banks of hours of the customer of the user and
  the tasks taken from them, read from the API of i-REST (version 1).
- View "All customers" of the page "Banks of hours": the banks of hours of every customer (current ones only or
  all, sorted by customer or by hours left, few or no hours left highlighted) and the detail of one customer.
- Right "Banks of hours" on the profiles, values "My customer" and "All customers" (both given at installation to
  the profiles that may update the configuration of GLPI, Super-Admin by default).
- Configuration (tab "iRest" of Setup > General): address of i-REST, key of its API (encrypted, write-only),
  time-out, test of the connection.
- Page "Preparation": prerequisites declared by i-REST (groups), checked and reported back, the missing ones
  created on demand.
- Mode "push" (version 1.1.0, needed by i-REST for this mode): i-REST writes its data in GLPI through the REST API of
  GLPI (object `GlpiPlugin\Irest\Document`, table `glpi_plugin_irest_documents`; each customer in its entity,
  recursive) and reads back the report of the prerequisites, so that GLPI never calls i-REST. Mode chosen in the
  configuration ("read" by default); right "Data sent by i-REST" for the profile of the API user of i-REST (given at
  installation to the profiles that may update the configuration of GLPI). The pages read the documents in this mode.
