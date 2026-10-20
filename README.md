# Borgbase for GLPI
<img src="https://raw.githubusercontent.com/ticgal/borgbase/multimedia/borgbase.png" alt="Borgbase Logo" height="250px" width="250px" class="js-lazy-loaded">

[Borgbase](https://www.borgbase.com/) integration for GLPI

[![License](https://img.shields.io/badge/License-GNU%20AGPLv3-blue.svg)](https://github.com/ticgal/borgbase/blob/master/LICENSE)
[![Twitter](https://img.shields.io/badge/Twitter-TICGAL-blue.svg)](https://twitter.com/ticgalcom)
[![TICGAL](https://img.shields.io/badge/Web-TICGAL-blue.svg)](https://tic.gal/)
[![Localazy](https://img.shields.io/badge/Translate-Localazy-cyan)](https://localazy.com/p/borgbase#translations)

# Prerequisites

You need a borgbase account. Get a 10 GB free account for life to test it here: https://www.borgbase.com/ 

# How to configure it

Requires GLPI 12.0. After installing the plugin, go to the GLPI configuration (Setup > General > Borgbase) and add the Borgbase API key. **Use a read-only key**: the plugin only reads data.

The server endpoint (`https://api.borgbase.com/graphql` by default) must be an https URL. Changing it clears the saved key, which must be entered again.

If the endpoint is reached through a private network address (on-premise mirror, internal proxy...), GLPI blocks the request unless it is allowed in `config/local_define.php`:

```php
define('GLPI_SERVERSIDE_URL_ALLOWED_PRIVATE_NETWORKS_CONTEXTS', [
    // keep the contexts GLPI allows by default (see src/Glpi/Application/SystemConfigurator.php)
    'Agent',
    'Auth',
    'GlpiPlugin\\Borgbase\\Config',
]);
```

Configure the permissions of the profiles that will manage the plugin:
- UPDATE: The user will be able to update the repository data manually.
- CREATE: The user will be able to create links between the computer and the repository if the name does not match.
- PURGE: The user will be able to delete manually created links.

## How to use it

By default, if the computer name exactly matches the repository name in Borgbase, the relevant data is stored and can be reviewed in the computer's tab.

Each computer will have a tab with its own Borgbase repository. It can be linked manually in case they do not match.
