# Borgbase — developer test record
<!-- testing-dev-format: 1 -->

Cumulative regression record. Append new steps at the end; never delete earlier ones.

## Step 1 — GLPI 12 migration and security fixes (3.0.0-beta.1, 2026-10-09)

Requirement: GLPI 12.0 support (`feature/glpi12`), security audit findings SEV-01…SEV-09.

### Test environment

The plugin has no PHPUnit suite. Verification is an HTTP battery driven against a running GLPI 12 instance plus a fake
BorgBase GraphQL server, kept outside the repository (local plugin memory, `plugins_memory/borgbase/tests_http/`):

- `mock_borgbase.py` — fake BorgBase API over TLS (`https://borgbase.test:18453`). Accepts the token `TESTKEY-123`,
  answers `isAuthenticated`, `repoList` (optionally filtered by the `name` variable), `repo` (by the `repoId`
  variable) and `overageList`, and records every request (path, `Authorization`, JSON body) to `requests.jsonl`.
  Extra paths: `/redirect` (302 to `/stolen`), `/slow` (15 s), `/garbage` (not JSON), `/errors` (GraphQL errors).
  Six repositories, one with HTML in its name/region and one with an invalid id.
- `env_up.sh [prod]` — starts the mock, installs a test CA in the GLPI container, adds `borgbase.test` to its
  `/etc/hosts`, writes a temporary `config/local_define.php` (strict mode: `GLPI_LOG_LVL=debug`, `GLPI_STRICT_ENV`;
  plus `GLPI_SERVERSIDE_URL_ALLOWED_PRIVATE_NETWORKS_CONTEXTS = [GlpiPlugin\Borgbase\Config]`). `env_down.sh` undoes it.
- `run_all.sh` → `t_all.py` — empties `php-errors.log` and runs the battery; any new log line during a test marks it `LOG`.

Run: `env_up.sh && run_all.sh; env_up.sh prod && run_all.sh; env_down.sh`. **T1 uninstalls/reinstalls the plugin.**

### Checklist

Lifecycle
- [x] T1.1 `plugin:uninstall` drops the 3 tables, the profile right, the cron task and the Computer display preferences 1468–1473.
- [x] T1.2 `plugin:install` + `activate`: config row 1 (`https://api.borgbase.com/graphql`, empty key, match=1), one cron task with itemtype `GlpiPlugin\Borgbase\Cron`.
- [x] T1.3 Upgrade from 2.x: legacy `PluginBorgbaseCron` task (custom frequency/state) and `PluginBorgbaseBorgbase` log links are renamed, data/config/cron settings kept; a second `--force` with both legacy and FQCN cron rows ends with one row (no unicity error).

Configuration (Setup > General > Borgbase, requires re-authentication)
- [x] T2.1 Tab shows server, key, match and Save.
- [x] T2.2 `front/config.form.php` GET redirects to the tab (`forcetab=GlpiPlugin\Borgbase\Config$1`).
- [x] T2.3 Saving server + key: key stored encrypted; API receives `Authorization: Bearer TESTKEY-123`; computers named like a repository are linked.
- [x] T2.4 Tab shows "Established connection"; neither the key nor its ciphertext appears in the HTML.
- [x] T2.5 `http://`, `javascript:`, `file://`, `gopher://`, userinfo URLs and garbage are rejected with a message; nothing saved.
- [x] T2.6 Changing the server without a new key clears the stored key; the new host never receives it.
- [x] T2.7 `_blank_apikey` (core "Clear" checkbox of the password field) clears the key; an empty key field keeps the stored one.
- [x] T2.8 Only `server`/`apikey`/`match` of row 1 are written (posted `id=5`, `debug=1` ignored).
- [x] T2.9 Cross-site POST → 403, no change. T2.10 POST without re-authentication → `/ReAuth/`, no change. T2.11 Technician (no `config` right) → 403.
- [x] T2.12 "Link repositories" button relinks by name and shows the count.

Profiles
- [x] T3.1 Profile tab shows the Borgbase rights matrix (Read, Update, Create, Purge). T3.2 Saving it stores the rights.

Computer tab (`front/borgbase.form.php`)
- [x] T4.1 Opening the tab of a computer named like a repository links it automatically (history entry); the API receives `query($name: String) { repoList(name: $name) … }` with `variables.name`, never the name inside the query.
- [x] T4.2 Unlinked computer: dropdown lists only free repositories with valid ids; HTML in repository names is escaped.
- [x] T4.3 Manual link + history "<repo>, by user glpi". T4.4 No double link (repository or computer already linked); unknown repository → "Repository not found".
- [x] T4.5 `repoId` with GraphQL/JSON injection, too long, empty, with spaces or as an array → 400, no API call, no DB change.
- [x] T4.6 Computer names carrying GraphQL/JSON injection reach the API only as the `name` variable; no `mutation` and no extra JSON keys.
- [x] T4.7 Reload refreshes the stored data. T4.8 Reload/unlink with a (computer, repository) pair that does not belong together, or an unknown computer → 404, nothing changed.
- [x] T4.9 Unlink deletes only that computer's row and relation; history entry. T4.10 Cross-site link → 403. T4.11 POST without action → redirect back.

Rights and entities
- [x] T5.1 Read-only profile: data visible, no buttons; reload/unlink/link → 403.
- [x] T5.2 No right: no tab, no data, POST → 403.
- [x] T5.3 Technician restricted to entity AT-B with all plugin rights: own computer auto-links; root-entity computers → no data, POST → 403/404, no change.
- [x] T5.4 Anonymous POSTs → redirected to login, no change.
- [x] T5.5 `/plugins/borgbase/front/{borgbase,config,profile,relation,cron,provider,dashboard}.php` (core generic list route) → 400/403, never a list or a 500.

Search
- [x] T6.1 Computer search on option 1468 with the six plugin columns; usage shown as "123.46 GB"; search on "Encrypted" works. T6.2 Without the right, the plugin columns are not available.

Dashboard
- [x] T7.1 The 5 cards render with mock data (usage history, quota %, repositories, current use, linked computers).
- [x] T7.2 "Computers with a repo linked" only counts the active entities (AT-B user sees 1).
- [x] T7.3 Without the plugin right, cards show no account data and make no API call.

Automatic action
- [x] T8.1 `php front/cron.php --force borgbaseUpdate` refreshes every linked repository; task log has a volume.

Outbound HTTP
- [x] T9.1 Without the allowed-context constant, the private-network endpoint is blocked (no request reaches the mock; `borgbase.log` says "blocked").
- [x] T9.2 302 answers are not followed. T9.3 Non-JSON, GraphQL-error and 404 answers: tab, config and all cards render without PHP errors.
- [x] T9.4 Slow API: the page returns after the 10 s idle timeout. T9.5 The key never appears in `borgbase.log`.
- [x] T9.6 Over the whole run, the API never received a `mutation` or extra JSON keys.

Static analysis
- [x] PHPCS, PHP-CS-Fixer, PHPStan (composer config; and the bundled phar without `vendor/`), TwigCS with the GLPI 12 ruleset.

## Step 2 — Security fixes from 2.0.1 (GLPI 11) brought to 3.0.0-beta.2 (2026-10-22)

Requirement: fixes reported by Teclib on the GLPI 11 line (`bugfix/securityissues`, 2.0.1-beta.1) present in GLPI 12.

- [x] T2.4 Config tab shows exactly one `_blank_apikey` checkbox (the core password field's "Clear"); the plugin no longer adds a duplicate.
- [x] T5.6 Profile with all Borgbase rights but read-only on Computers (Observer): tab shows data without Reload/Unlink/Link buttons; the three POST actions → 403; no DB change.
- [x] T4.8/T4.9 still green with the stricter unlink (rows deleted only when repository, computer and relation belong together).
- [x] T7.x still green with the explicit right check in the usage providers.
- [ ] `Config::$undisclosedFields = ['apikey']`: not reachable over HTTP in this setup (API v1 answers `ERROR_RESOURCE_NOT_FOUND_NOR_COMMONDBTM` for the plugin config itemtype); verified by code only, defence in depth.
- [x] Locales: `tools/extract_template.sh` now extracts `_n`/`_x`/`_sx`/`_nx` (plural "%d repository linked" kept in the .pot).
- [x] Run: HTTP 47/47 + browser 6/6, strict and production; `php-errors.log` without plugin entries. PHPCS, PHP-CS-Fixer, PHPStan (composer and phar), TwigCS green.

## Step 3 — Real BorgBase API (2026-10-22)

Requirement: the plugin works against the real API (`https://api.borgbase.com/graphql`, read-only key), not only the mock.

- [x] Bug found and fixed: the real schema is `repo(repoId: String!)`; the query declared `$repoId: String` and the API rejected it
  ("Variable '$repoId' of type 'String' used in position expecting type 'String!'"), breaking manual link, reload and the
  automatic action. Fixed (`String!`) in 3.0.0-beta.2 and in the 2.0.1 branch. The mock now rejects the nullable declaration too.
- [x] `real_smoke.py` (local plugin memory, never uninstalls nor changes server/key; read-only on BorgBase; purges its «BB real *»
  computers): connection, real repo dropdown, auto-link by name, reload, manual link + unlink, 5 dashboard cards, automatic action,
  search, no API errors in `borgbase.log` — 9/9 in strict mode.
- [x] Browser against the real API: config "Established connection" with one "Clear" checkbox, computer tab with formatted usage,
  reload, no JS errors.
- [x] Real data notes: `overageList` is empty for the test account (usage cards show no data / 0, correct); repo `currentUsage`
  values (8.08, 12.08, 30.08) are shown as MB.
