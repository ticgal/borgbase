# Borgbase - GLPI Plugin CHANGELOG

## [2.0.1] - 2026-10-09
### Security
- API queries no longer concatenate the computer name or the repository id: GraphQL variables are used and repository
  ids are validated (a crafted computer name could send arbitrary GraphQL to the BorgBase account)
- Linking, reloading and unlinking repositories require the update right on the computer (entity) and check that the
  repository belongs to it; missing rights now return an error instead of being silently ignored
- The server endpoint must be an https URL; changing it clears the saved authentication key. API calls only use https,
  never follow redirects and have a timeout
- Only the server, key and matching option of the configuration can be saved
- Dashboard cards require the Borgbase read right and only count computers of the active entities
- The encrypted API key is no longer exposed through the API (undisclosed field)

### Changed
- Uninstalling the plugin now deletes its tables (configuration, including the API key, and repository links)
- The automatic action is registered at install time instead of on every page load
- "Clear the saved key" option in the configuration

### Fixed
- Automatic linking from the computer tab no longer breaks the tab with a wrong redirect
- Unlinking a repository could delete the link of another computer
- Errors when the API is unreachable or returns unexpected data (computer tab, dashboard cards, automatic action)

## [2.0.0] - 2026-01-27
### Features
- GLPI 11 support

## [1.1.6] - 2025-01-29
### Fixed
- Avoid losing data if the repository no longer exists in Borgbase
- Avoid php errors when repository don't exist

## [1.1.5] - 2025-01-27
### Fixed
- Fix borgbase api responses (#26621)

## [1.1.4] - 2025-01-24
### Fixed
- Dashboard KPIs did not work (#26586)

## [1.1.3] - 2025-01-10
### Fixed
- Cron for reloading data, avoiding empty or inexistent repos

## [1.1.2] - 2024-12-27
### Added
- More searcheable fields

### Fixed
- Fix php typing (#25945)

## 1.1.1
### Bugfixes
- Fix PHP Deprecated function (#23377)

## [1.1.0]
### Added
- Search Options for computers

## 1.0.0

## 0.7.1 - 2022-11-23
- Automatic link (#12348)

### Bugfixes
- New KPI overwrite dashboard cards list (#12245)
- Dashboard card returns 0 repositories (#12310)

## 0.6.2 - 2022-11-10
### Features
- Dashboard graphs (#12093)

### Bugfixes
- READ permissions (#12140)
- Fixing other errors (#12145)

## 0.5.0 - 2022-11-08
### Features
- API connection indicator (#12087)
- Confirm buttons (#12090)

## 0.4.2 - 2022-11-07
### Features
- Permissions (#12062)
- Historial (#11970)
- Spanish and Galician translations (#11957)

### Bugfixes
- Changing mysql tables engine to InnoDB (#12027)
- Fixing wrong calculation in unit bytes (#12019)

## 0.3.0 - 2022-10-28
### Features
- Unlink repositories (#11865)
- Dropdowns instead classic inputs (#11880)
- Searcheable dropdown (#11930)

## 0.2.0 - 2022-10-20
### Features
- Automatic actions (#11755)

## 0.1.0 - 2022-10-17
### Features
- MVP (#11626)