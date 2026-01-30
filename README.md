# Borgbase for GLPI
<img src="https://raw.githubusercontent.com/ticgal/borgbase/multimedia/borgbase.png" alt="ActualTime Logo" height="250px" width="250px" class="js-lazy-loaded">

[Borgbase](https://www.borgbase.com/) integration for GLPI

[![License](https://img.shields.io/badge/License-GNU%20AGPLv3-blue.svg)](https://github.com/ticgal/borgbase/blob/master/LICENSE)
[![Twitter](https://img.shields.io/badge/Twitter-TICGAL-blue.svg)](https://twitter.com/ticgalcom)
[![TICGAL](https://img.shields.io/badge/Web-TICGAL-blue.svg)](https://tic.gal/)
[![Localazy](https://img.shields.io/badge/Translate-Localazy-cyan)](https://localazy.com/p/borgbase#translations)

## Supported versions
- GLPI 10.0.x

# Prerequisites

You need a borgbase account. Get a 10 GB free account for life to test it here: https://www.borgbase.com/ 

# How to configure it

After plugin installation head to the GLPI configuration (Setup > General > Borgbase) and add the Borgbase API key. A read-only one is enough.

Configure the permissions of the profiles that will manage the plugin:
- UPDATE: The user will be able to update the repository data manually.
- CREATE: The user will be able to create links between computer and repository, if the name does not match.
- PURGE: The user will be able to delete manually created links.

## How to use it

By default, if the computer name exactly matches the repository name in Borgbase, the relevant data is stored and can be reviewed in the tab of the computer itself.

Each computer will have a tab with its own Borgbase repository. It can be linked manually in case they do not match.
