# Changelog

All notable changes of iRest for GLPI. Versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
