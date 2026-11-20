# centreon-web split (httpd / php-fpm) — build from source

First draft of Pilier 1 (["Split httpd/php-fpm"](https://centreon.atlassian.net/wiki/spaces/RD/pages/3823730701)):
two independent production images for `centreon-web`, built from source
(composer + pnpm), with zero shared volume between them. See the design
notes in the plan this was generated from for the decisions behind the
choices below (base images, `www/` partition rule, why cache isn't warmed at
build time, ...).

## Quick start

From the repository root, builds both images and wires them to a real
MariaDB (see `../docker-compose.yml`):

```bash
docker compose -f .github/docker/centreon-web-split/docker-compose.yml up --build
```

Then open `http://localhost:18080/centreon/` (trailing slash, see the
`HTTPD_SERVER_NAME` note below) and log in with `admin` / `Centreon!2021`.

## Build only (no compose)

```bash
docker build --target httpd -f .github/docker/centreon-web-split/trixie/Dockerfile -t centreon-web-httpd-trixie .
docker build --target fpm   -f .github/docker/centreon-web-split/trixie/Dockerfile -t centreon-web-fpm-trixie .
```

## Runtime env vars

| Image | Var | Purpose |
| --- | --- | --- |
| `httpd` | `HTTPD_SERVER_NAME` | `ServerName` / `UseCanonicalName On` — fixes `MonitoringServerConfigurationRepositoryApi::initUri()`'s internal-URL bug |
| `httpd` | `PHP_FPM_UPSTREAM` | `host:port` of the php-fpm service (`fcgi://` proxy target) |
| `httpd` | `GORGONE_WSS_UPSTREAM` | `host:port` of the central gorgone's `proxy.httpserver` websocket listener (Pilier 2) — httpd terminates TLS and reverse-proxies `${base_uri}/gorgone/pullwss/websocket` to it, matching `centreon/packaging/src/centreon-apache.conf`'s reference config. Not the top-level `httpserver` REST API module, a separate listener. |
| `fpm` | `CENTREON_INTERNAL_API_BASE_URL` | fixes `InternalApiClient`'s internal-URL bug (points back at the httpd service) |
| `fpm` | `RUN_INSTALL=true` | dev/CI only (see below) — runs a real, non-interactive install against `MYSQL_HOST`/`MYSQL_ROOT_PASSWORD` before starting php-fpm |
| `fpm` | `PHP_TIMEZONE` | `date.timezone` in `centreon.ini`, resolved from a template at container start (`01-config.sh`, same convention as httpd's `10-config.sh`) — defaults to `UTC` in the image, same fallback the real packaging postinstall's `setPhpTimezone()` uses when it can't detect a host timezone; a real VM has no equivalent env var, it detects `/etc/timezone`/`date "+%Z"` instead |
| `fpm` | `APP_SECRET` / `SALT` | fixed dev secret pair (`docker-compose.yml` sets defaults) written straight into `/etc/centreon-engine/engine-context.json` and used to resolve `.env`'s `%APP_SECRET%` placeholder — without them, `12-install.sh` falls back to fresh random values on every container recreate. Since neither `.env*` nor `engine-context.json` survive a recreate (no volume) but the `db` service does, random values would silently invalidate sessions and orphan anything already encrypted in the DB under the previous pair. `gorgone-central-poller`/`centengine` are given the *same* pair (not independent placeholders) — engine has to decrypt the same data web encrypted. |

## Testing against a real database, manually (without compose)

`fpm`'s `container.d/12-install.sh` is an **opt-in** (`RUN_INSTALL=true`) trimmed
port of the monolithic image's own `15-installation.sh` + JSON answer-file
fixtures (`.github/docker/centreon-web/trixie/configuration/*.json`, baked
into this image too) — it runs the real non-interactive install (same PHP CLI
scripts, same DB schema) against an external MariaDB, skipping only the
gorgone/engine-context steps this image has no package for. This is how the
end-to-end login smoke test from the pillar-1 page was reproduced here.

```bash
docker network create centreon-split-net

docker run -d --name centreon-db --network centreon-split-net \
  -e MARIADB_ROOT_PASSWORD=centreon -e MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 \
  -e MARIADB_USER=centreon -e MARIADB_PASSWORD=centreon \
  mariadb:11.8 --skip-ssl --skip-name-resolve   # same image/creds as .github/docker/docker-compose.yml + .env

docker run -d --name centreon-fpm --network centreon-split-net \
  -e RUN_INSTALL=true -e MYSQL_HOST=centreon-db -e MYSQL_ROOT_PASSWORD=centreon \
  -e CENTREON_INTERNAL_API_BASE_URL=http://centreon-httpd:80 \
  centreon-web-fpm-trixie

docker run -d --name centreon-httpd --network centreon-split-net \
  -e PHP_FPM_UPSTREAM=centreon-fpm:9000 -p 18080:80 \
  -e HTTPD_SERVER_NAME=localhost:18080 \
  centreon-web-httpd-trixie
```

`HTTPD_SERVER_NAME` matters here: with `UseCanonicalName On` (needed to fix one
of the two internal-URL bugs, see the Dockerfile comments), Apache rebuilds
every self-referential URL — including the automatic `/centreon` →
`/centreon/` redirect — using this value, not the `Host:` header the browser
actually sent. Leave it at the default (`centreon-httpd`) and a browser
hitting the port-mapped `localhost:18080` gets redirected to an
unresolvable `http://centreon-httpd/...` (`DNS_PROBE_POSSIBLE`). Set it to
whatever host:port the browser will actually use.

Login fixture (`.github/docker/centreon-web/trixie/configuration/admin.json`,
same one CI uses): **admin / Centreon!2021**. Verified working end-to-end
(2026-09-28) with:

```bash
curl -s -X POST http://localhost:18080/centreon/api/latest/login \
  -H "Content-Type: application/json" \
  -d '{"security":{"credentials":{"login":"admin","password":"Centreon!2021"}}}'
# -> HTTP 200, {"contact":{...,"is_admin":true},"security":{"token":"..."}}
```

`docker inspect centreon-httpd|centreon-fpm --format '{{.Mounts}}'` both
return `[]` — confirms zero shared volume, same as the pillar-1 page's own
smoke test.

Re-running with the same (persisted) MariaDB and a fresh `fpm` container is
safe — `12-install.sh` checks for an existing schema (`nagios_server` table)
before re-running the schema installers.

## Pilier 2 — gorgone-central (pullwss), config-delivery mechanism

`docker-compose.yml` also brings up a central-orchestrator gorgone
(`gorgone-central`) plus Central's own engine acting as a poller for
config-delivery purposes (`gorgone-central-poller`, the existing unmodified
`TYPE=poller` image) — the architecture validated end-to-end in a separate
`centreon-collect` session (websocket handshake through `httpd`'s
`GORGONE_WSS_UPSTREAM` reverse proxy, `REMOTECOPY`, `COMMAND`/reload). `fpm`
and `gorgone-central` share the `centreon-cache`/`centreon-varlib` named
volumes (`/var/cache/centreon`, `/var/lib/centreon`) — `REMOTECOPY` reads
source files off gorgone's own disk, never from the triggering request, so
this has to be the literal same filesystem `fpm` writes to, not a copy (see
`www/include/configuration/configGenerate/xml/moveFiles.php`).

`gorgone-central-test:src` (default `GORGONE_IMAGE`) carries a one-word fix —
`gorgone/gorgone/modules/core/register/class.pm`: `REGISTERNODES` →
`REGISTERNODESFROMDB` in `action_registerresync` — not yet merged upstream,
without which `gorgone-central` can never self-register (`register_nodes()`
can only update an already-known node, never create one; only
`register_nodes_from_db()` can). Override `GORGONE_IMAGE` once it lands on
the real `centreon-gorgone-trixie` image.

Two permission gaps found only by actually running this (not assumed):
- Named volumes start root:root regardless of what the image chowns at
  build time — `poller-broker-init` handles this (plus real content seeding)
  for broker's config volume; the equivalent for the fpm↔gorgone cache/varlib
  pair is folded into the `fpm` Dockerfile now (see below), no separate init
  service for that pair any more.
- `/var/cache/centreon`/`/var/lib/centreon` need to be writable by **both**
  `www-data` (fpm) and `centreon-gorgone` (gorgone-central, a different user)
  — `fpm`'s Dockerfile creates the same fixed UIDs/GIDs every Centreon
  container image uses (`centreon`/`centreon-engine`/`centreon-broker`/
  `centreon-gorgone`, 900-903), cross-adds `www-data`↔`centreon-gorgone`
  group membership, and chowns/chmods 775 those two paths directly at build
  time. **Corrected 2026-09-29**: this used to be a runtime fixup script
  (`container.d/01-fix-volume-ownership.sh`, chowning at container start
  since which container populates the shared volume first isn't guaranteed
  by `depends_on`) — moved into the Dockerfile instead, since the image
  already owns these paths at build time regardless of mount order once the
  volume itself starts empty; the runtime script is deleted, not just
  disabled.

A real `centengine` service is also part of the stack now (`ENGINE_IMAGE`,
default `docker.centreon.com/centreon/centreon-engine-trixie:develop`),
sharing `/etc/centreon-engine`, `/etc/centreon-broker` and
`/var/lib/centreon-engine/rw` with `gorgone-central-poller` — same 3-volume
pairing `centreon/install-poller`'s own generated compose uses. It needs the
*same* `APP_SECRET`/`SALT` pair `fpm` writes into `engine-context.json` (see
the env var table above), not independent placeholder values.

Verified for real (2026-09-28): `[pullwss] websocket connected` (poller) /
`[proxy] httpserver client 1 is logged` (central) in the logs, a full
`REMOTECOPY` round trip via `curl` (write a file as `gorgone-central`, POST
`/api/nodes/1/core/proxy/remotecopy`, read it back from
`gorgone-central-poller`) — content matched — and `centengine` reaching
"Configuration loaded, main loop starting." in its own logs. `moveFiles.php`/
`restartPollers.php` were never touched, but clicking "Generate Configuration
Files" in the web UI against `localhost=No` for Central *did* go through
successfully at least once (confirmed by inspecting `broker-context` — a real
`central-broker.json` with `db_host: db` and an `encrypt::`-wrapped
`db_password` landed there via this exact mechanism), so more of this path is
already exercised than initially assumed.

### Reducing hardcoded values in `40-gorgoned-central-pullwss.yaml`

That file bundles what a real central splits across `config.d/30-centreon.yaml`
/ `31-centreon-api.yaml` / `39-action.yaml` / `40-gorgoned.yaml` /
`41-autodiscovery.yaml` / `50-centreon-audit.yaml`, with real credentials
checked into the file. Tried converting the obvious candidates to
`GORGONE__...` env vars in `docker-compose.yml` before assuming it would
just work everywhere:

- **DB DSN: safe, confirmed twice.** A deliberately wrong value produced a
  real `"MySQL error : cannot connect to database ... Unknown server
  host"`; removing the `dsn:` line from the YAML entirely and letting the
  env var supply it alone still let `nodes` resync cleanly.
- **`GORGONE__GORGONE__MODULES__httpserver__auth__user`/`__password`
  silently broke the module at first** — gorgone logged a successful
  "updated the configuration" for both, then `"[httpserver] User option
  mandatory if authentication is enabled"` / `"Module 'httpserver' cannot
  be loaded"`, and the healthcheck didn't catch it (only watches the
  separate proxy/pullwss port). Root cause, found on the gorgone-side
  session: `load_env_config` (`gorgone/class/script.pm`) lowercases the env
  var name to match it against the YAML path, then re-fetches the actual
  value via `$ENV{uc($key_env)}` — that round-trip only recovers the real
  value if the env var's own name was entirely uppercase to begin with. Any
  lowercase segment (`httpserver`, `auth`, `user` — a completely natural
  way to type it) makes the reconstructed uppercase key miss the real env
  var, `$ENV{...}` returns undef, and the YAML's value gets silently
  discarded while still logging success. Same bug affected
  `GORGONE__GORGONE__MODULES__pullwss__ssl` on the poller side too, though
  it happened to look fine there, since `undef` and `"false"` both fail the
  module's own `eq 'true'` check — correct behavior by accident. **Fixed by
  using all-uppercase segments everywhere** —
  `GORGONE__GORGONE__MODULES__HTTPSERVER__AUTH__USER`/`__PASSWORD` and
  `GORGONE__GORGONE__MODULES__PULLWSS__SSL` — confirmed clean with the
  YAML's own `user`/`password` keys removed entirely, no fallback. Also
  fixed gorgone-side (`script.pm` keeps the env var's original-case name
  for the value lookup instead of reconstructing it via `uc()`).
- **`GORGONE__GORGONE__TPAPI__centreonv2__base_url`: crashes gorgoned
  outright, unrelated to casing.** Same list-by-name pattern that works for
  `modules`, but `tpapi` isn't structured the same way internally — only
  `modules` gets the special array-search-by-name handling in
  `load_env_config`, `tpapi` falls through to plain hash-key access on an
  arrayref — `"Not a HASH reference at gorgone/class/script.pm line 341"`.
  Confirmed still true regardless of casing. Not attempted again without a
  fix on the gorgone side.

Sent the full findings plus the user's real central's `config.d/` layout to
the gorgone-side session, which then built and shipped the real fix: native
`TYPE=central` support (`container.d/10-create-config.sh` in
`centreon-collect`, generates `config.d/{30-centreon,31-centreon-api,
40-gorgoned}.yaml` at startup from env vars, no `command:` override needed
any more than for `TYPE=poller`). Wired in here: dropped
`40-gorgoned-central-pullwss.yaml` and its bind mount entirely, added
`CENTREON_API_BASE_URL`/`CENTREON_API_USERNAME`/`CENTREON_API_PASSWORD`,
kept the DB DSN override. `gorgone-central` itself confirmed clean:
`"Web application available at http://0.0.0.0:8087"`, `nodes` resync
succeeds, node registers ("message inserted").

**Real Centreon-API-token gap closed.** The real `40-gorgoned.yaml`'s
`proxy.httpserver` block has no static `token:` field (confirmed by reading
the generated file directly) — our old `GORGONE_TOKEN` env var on
`gorgone-central-poller` was the *other* pullwss auth path (a plain shared
secret matched against exactly that field, see the "Node self-registration"
section above), which no longer has anything to match against now that the
real config doesn't set one. pullwss connected, then
`{"code":500,"message":"invalid token"}`, connection closed. Fixed with a
real `name:value` Centreon API token instead: `POST
/centreon/api/latest/administration/tokens` with `{"name": "poller-default",
"expirationDate": null, "type": "poller", "userId": 1}` (`type` must be
exactly `poller`, checked by gorgone's own `httpserver.pm`), `GORGONE_TOKEN`
set to `poller-default:<the returned plaintext>`. Verified end to end:
`"[pullwss] websocket connected"`, no further auth error, container stable
`healthy`.

The token *name* "poller-default" turns out to already exist by default on
a fresh install (confirmed: nobody created it by hand, same for a
`Central-<hash>` CMA-type token alongside it) — so the create call above
will return `409 The token name 'poller-default' already exists` on this
stack most of the time, which is fine: `GET
/centreon/api/latest/administration/tokens/poller-default/users/1`
(authenticated) returns the plaintext value directly for an existing token
too, not only at creation time.

**No more manual re-fetch, no more 2-step startup.** The *value* is
install-specific (freshly random per install), so a hardcoded
`GORGONE_TOKEN` in `docker-compose.yml` went stale after every genuinely
fresh `docker compose down -v`. Fixed natively on the gorgone side
(2026-09-30, `centreon-collect`): `gorgone-central-poller` now logs into the
Centreon API itself at its own startup and fetches the token before
`gorgoned` ever launches, gated behind `PULLWSS_TOKEN_NAME` (unset = old
static-value behavior, opt-in only, not folded into a general poller
template — a real remote poller gets its token the normal way, Add Poller
UI, and handing it Centreon admin-API credentials on top would be a real
privilege increase for no reason). `CENTREON_API_BASE_URL`/`USERNAME`/
`PASSWORD` (same three names the `TYPE=central` wiring above already uses)
plus `PULLWSS_TOKEN_FETCH_RETRIES` (bumped to 90 here, since the default
budget of ~2 minutes can plausibly lose a race against a genuinely fresh
install's full schema setup — `depends_on` only waits for `httpd` to start,
not for `RUN_INSTALL` to finish) and `restart: unless-stopped` as the real
safety net regardless. Verified twice, not once: two full
`docker compose down -v && up` cycles in a row, each producing a different
token value, both auto-fetched correctly with zero manual steps —
`"✓ pullwss token fetched for 'poller-default' (GORGONE_TOKEN set)"` then
`"[pullwss] websocket connected"`, all 7 services healthy, login
regression-checked after each.

### `centreon-broker` (central-broker-master)

`docker-compose.yml` also runs a real `centreon-broker` service
(`BROKER_IMAGE`, default `docker.centreon.com/centreon/centreon-broker-trixie:develop`)
— the BBDO dispatcher `centengine`'s cbmod (`central-module-master`, running
*inside* centengine, not a separate container) streams events to. Two bugs
found and fixed only by actually running it (2026-09-29), neither guessable
from reading config alone:

- **`cbd`'s db_password decryption needs its own `engine-context.json`,
  deliberately not shared with `centengine`/`gorgone-central-poller`.**
  `central-broker.json`'s `db_password` is `encrypt::...`, decryptable only
  via an AES key built from `/etc/centreon-engine/engine-context.json`
  (`broker/core/src/main.cc`'s `reload_engine_context()`). Fabien's call: no
  shared volume between engine and broker for this — `centreon-broker`'s
  `command:` wrapper writes its own private copy of that file from
  `APP_SECRET`/`SALT` env vars (same pair as everywhere else) before
  `su`-ing down to the `centreon-broker` user and exec'ing `cbd`. Without
  it: `"encrypted password but no decrypt enabled"`, logged to
  `/var/log/centreon-broker/central-broker-master.log` — **not** stdout
  (that config's `"filename": ""` routes all broker logging to a file once
  applied), which is why `docker logs` showed nothing but the startup
  banner and made this look silent/mysterious.
- **`cbd -c <path>` is not "config file path" — `-c`/`--check` means
  "validate config and exit", no argument; the path is picked up as a
  positional argument regardless, which is what made this so misleading**:
  config loads and applies correctly, `cbd` then skips its own `while
  (!gl_term)` event loop and runs a full, orderly deinit — a clean
  `exit_code:0` a few seconds after every start, logging a completely
  normal-looking startup-then-clean-shutdown sequence with no error, no
  crash. Cost real debugging time: removing the (`localhost`-pointed, thus
  guaranteed-to-fail) RRD output, setting `tls: no`, and aligning
  `bbdo_version` between `central-module.json`/`central-broker.json` were
  all tried first and — correctly — changed nothing, since none of them
  touch the actual cause. Fix: drop `-c`, invoke as
  `cbd /etc/centreon-broker/central-broker.json`.
- **`cbmod` (inside `centengine`) defaults to `localhost:5669`** for its own
  output. Root cause is in the database, not the image: `cfg_centreonbroker_info`
  for `central-module-master` (`config_id=3`) ships `host=localhost` by
  default (`insertBaseConf.sql`) — correct for a monolithic/VM install
  (broker co-located), wrong once broker is its own container. First fixed
  with a bind-mounted static `central-module.json` overriding the image's
  own file; that masked the real generated file and would have silently
  blocked any future `REMOTECOPY` delivery to that same path (a read-only
  bind mount can't be written to). Fixed properly instead: `12-install.sh`
  now patches that one DB row (`UPDATE cfg_centreonbroker_info SET
  config_value = 'centreon-broker' WHERE config_id = 3 AND config_key =
  'host' ...`), no bind mount needed any more. Verified for real: a genuine
  "Generate Configuration Files" + "Move Export Files" against this DB
  produces a `central-module.json` with `"host": "centreon-broker"` on its
  own (inspected directly on `gorgone-central-poller`'s own view of the
  volume), and `centengine`'s log shows `"Poller 'central-broker-master'
  with id 1 connected"` after a restart with no override in place.

Verified for real: `centengine`'s own log reaches `"Poller
'central-broker-master' with id ... connected"`, `centreon-broker` stays
`Up`/`healthy` (no more restart loop), login regression-checked again.

**Switched to central-gorgone's `MON-210954-broker-log-streaming-secrets-reload-shim`
image, `poller-broker-init` dropped (2026-10-01).** That image adds a
fingerprint guard refusing to start on the package-default config — this
stack's own `poller-broker-init` used to seed exactly that default into
the (otherwise empty) `poller-broker` volume before any real config
existed, which is the one scenario the guard exists to refuse. Removed
rather than adapted: cross-checked with central-gorgone first (not
assumed safe) — their own testing (3 scenarios: empty volume, config
without the fingerprint file, real config + reload/restart) found and
fixed two real bugs in their own image triggered by exactly this
scenario (their fingerprint file lived at the same path our volume
mounts over, silently disabling their own guard; their new
`api_control.py` wrapper didn't actually wait on `cbd`'s exit, so a
crashed `cbd` still reported `/health` as healthy) — both fixed before
confirming a genuinely empty volume is correctly handled. The real
config now always arrives via the "Automated config delivery" background
script above, not a manual click, and not a seeded default either.

**Verified for real, fresh install**: `centreon-broker` came up
`healthy` on the very first attempt (`RestartCount: 0` — the automated
delivery script's first successful call landed before broker's own
first start, in this run), `central-broker.json` content confirmed real
(broker_id/poller_id/uid populated, not package-default shape),
`centengine` connected, login `200`.

**`poller-etc` volume wired to `/etc/centreon` on `gorgone-central-poller`,
2026-10-05.** Previously declared in `volumes:` but never mounted
anywhere — Fabien corrected an earlier claim that it was dead/leftover:
it's meant to persist config files "Generate Configuration Files"
delivers there for feature-specific components that run in their own
container, e.g. `centreon-vmware`'s `/etc/centreon/centreon_vmware.json`.
Confirmed real rather than assumed: observed the actual REMOTECOPY
destination in `gorgone-central-poller`'s own logs
(`"destination":"/etc/centreon/centreon_vmware.json"`) — it fires on
every config generation regardless of whether a VMware poller is
actually configured in this dev stack. Without the volume, that delivery
landed in the container's ephemeral filesystem and vanished on recreate.
Wired the same way as `poller-engine`/`poller-broker` (written by
`gorgone-central-poller`, to be read by a future `centreon-vmware`
container — not part of this stack yet, just available locally per
Fabien). Verified for real: `centreon_vmware.json` lands after a
"Generate Configuration Files" call and survives a
`--force-recreate gorgone-central-poller`.

**Two real bugs found and fixed, 2026-10-05, after a Docker Desktop
restart surfaced them** (restarting already-stopped containers, not a
fresh `down -v` install, is a different race window than every earlier
test had exercised):

1. **The REST endpoint's `204` doesn't mean the config actually arrived.**
   `generate-and-reload` returned `204` while `gorgone-central-poller`'s
   `pullwss` module was still loading — the API call completed, but the
   downstream gorgone REMOTECOPY had nowhere connected to land on yet, so
   nothing was actually delivered. The script's original `while true; do
   if success: break; done` treated that `204` as "done forever," so
   `centreon-broker` stayed on the package-default config, crash-looping,
   until a manual re-run delivered it for real. Fixed by never stopping
   the loop at all — it keeps calling `generate-and-reload` on a 60s
   interval for the container's whole lifetime instead of exiting after
   one apparent success. Cheap and idempotent (same as clicking "Generate
   Configuration Files" twice), and self-heals this exact race without
   needing to verify gorgone's connection state directly.
2. **A second, more fundamental bug found while testing the fix above:**
   even with the loop restructured, the *first* failed attempt (the
   expected case on every fresh install — gorgone isn't connected yet)
   still killed the whole script outright, before it ever reached its
   first `sleep`. Root cause: `container.sh` runs `set -e` before
   backgrounding `*_background*` scripts via `. "$file" &`, and this
   image's `/bin/sh` is `dash`, which propagates `set -e` into that
   backgrounded subshell. A non-zero exit from the `su`/`php` call inside
   the loop's *body* (not its controlling condition, so not `-e`-exempt)
   terminated the subshell immediately. Confirmed via `ps aux` inside the
   running container: the backgrounded script's PID was gone entirely —
   not hung, not a zombie — while a manual `docker compose exec` re-run of
   the identical script worked fine, because a fresh `exec` shell never
   inherits container.sh's own `set -e` in the first place (exactly why
   this never showed up under manual testing, only under the real
   container-startup code path). Fixed with an explicit `|| true` on the
   `su` call. **Verified for real after both fixes**: a full
   `docker compose down -v && up -d --build` now shows the script surviving
   its first failed attempt, retrying after the 60s sleep, and delivering
   a real config on the second try — `centreon-broker` reaches `healthy`
   on its own via `restart: unless-stopped`, no manual intervention.

**Gorgone connection-status polling implemented (2026-10-05, Fabien's
call: "intègre le").** `generate-broker-configuration.php` now polls
gorgone's own `GET /internal/constatus` (confirmed reachable at
`http://gorgone-central:8085/api/internal/constatus`, plain HTTP, no
auth — `GORGONE_API_BASE_URL` env var, same id as `CENTRAL_POLLER_ID`
since gorgone's node numbering and Centreon's monitoring_server id
happen to coincide in this topology) for up to 55s before attempting
delivery, checking `ping_ok`/`last_ping_recv` go non-zero (confirmed
empirically: both read `0` for a few seconds right after the websocket
connects, through the first ping cycle, so this is a real "actually
talking" check, not just "socket open"). A latency optimization, not a
new correctness dependency: on a constatus timeout or any failure
reaching gorgone's API, the script falls through to attempting delivery
anyway, and the outer shell loop's retry-forever (the `|| true` fix
above) remains the actual correctness guarantee regardless. **Verified
for real**: a full fresh `down -v && up -d --build` now shows
`"Gorgone node 1 connected, proceeding."` followed immediately by
`"Configuration generated and delivered... (HTTP 204)"` — no wasted
failed attempt beforehand, unlike the blind-retry version.

### Real "Add Poller" flow: two more real bugs, found by actually testing it (2026-10-05)

Fabien used the (now-working) `install.sh` to actually add a poller
against this stack and hit two separate problems — neither hypothetical,
both confirmed and fixed.

**1. A freshly-added pullwss poller never connected without restarting
`gorgone-central`.** Its own gorgone kept retrying forever:
`websocket connected` → `{"code":500,"message":"please registernodes"}`
→ closed, every 60s. Root cause, not guessed: `options.gorgone_api_address`
ships `127.0.0.1` by default (`insertBaseConf.sql`), correct for a
co-located VM install, wrong in this split topology. That's what
`Centreon\Domain\Gorgone\ConfigurationLoader` reads to build the URL for
`triggerGorgoneNodesSyncIfPullwss()`
(`www/include/configuration/configServers/DB-Func.php`) — the call that's
supposed to fire automatically every time a poller is saved, telling
`gorgone-central` to re-sync its node list immediately. With the stale
`127.0.0.1` value, that call silently fails from inside `fpm` (fire-and-
forget by design: "a Gorgone outage cannot break the poller save") and
`gorgone-central` never learns about the new node — only a full restart
(which re-registers every node from the DB at startup) papered over it.
**Verified precisely, step by step**: manually `POST
/api/centreon/nodes/sync` against `gorgone-central` — the poller
connected within one retry cycle, no restart. Fixed in `12-install.sh`:
`UPDATE options SET value='gorgone-central' WHERE key='gorgone_api_address'`,
same pattern as the other co-located-VM-assumption DB rows already
patched there (`cfg_centreonbroker_info`, `nagios_server.localhost`).
Re-verified on a genuinely fresh install with no manual sync call at
all: the poller registered and started ping-exchanging with
`gorgone-central` (`GET /internal/constatus` showing real `ping_ok`/
`last_ping_recv` for it) on its own.

**2. `centreon-broker`'s compose override was quietly bypassing the new
image's own `api_control.py` reload/restart shim.** Fabien asked
directly: "on a toujours besoin de notre entrypoint custom ?" — good
question, the answer was no, mostly. Reading the image's real
`container.sh` showed it already writes `/etc/centreon-engine/
engine-context.json` natively from `APP_SECRET`/`SALT` (same env vars we
already pass) and its real default `CMD` is `python3 .../api_control.py
.../central-broker.json`, not a raw `cbd` invocation — our own override
was hand-rolling both of those *and*, by calling `cbd` directly, entirely
skipping `api_control.py`'s HTTP reload/restart/health shim (the actual
point of this image branch). Simplified the override to just the one
thing the image genuinely doesn't know about — `chown`-ing the two
custom RRD directories (`/var/lib/centreon/status`/`metrics`, fresh named
volumes with no image-provided ownership) — then handing off to the
image's real entrypoint+cmd chain instead of reinventing it. Also
exposed port `8080` (the shim's own port, confirmed by reading
`api_control.py`: `POST /reload`, `POST /restart`, `GET /health`) so it's
reachable without another compose edit later, even though nothing in
this stack calls it yet. **Verified for real**: fresh install, `GET
/health` → `{"cbd_running":true,"pid":...}` (`200`), `POST /reload` →
`{"reload":"sent SIGHUP"}` (`200`), stack stayed healthy afterward, log
streaming still shows full real `cbd` activity (protocol registrations,
`central-module-master` connecting), login still `200`.

**Taken further the same day — Fabien pushed twice more: "on a toujours
besoin de ça dans le compose broker ?", then "on peut pas avoir le
volume correctement monté par fpm ?"** The one remaining piece (chowning
the two RRD directories) still needed `user: root` on the whole service.
First move: a dedicated one-shot init service doing just that chown,
letting `centreon-broker` itself run with zero overrides — then Fabien
pointed out pulling the (large) broker image just for a `mkdir`+`chown`
is wasteful when `fpm` already runs an early root-level `container.d`
phase and already bakes in `centreon-broker`'s exact UID/GID (added
earlier for cross-container volume consistency). Moved the chown into a
new `container.d/03-rrd-volumes.sh` on `fpm` instead — numbered to run
within the first second of `fpm`'s own startup, well before
`12-install.sh`'s lengthy `RUN_INSTALL` work. Required dropping `:ro`
from `fpm`'s own mount of these two volumes (the mount-level flag applies
to every process in that container equally, including root during this
one startup script — there's no way to let only the startup phase write),
though the application code itself still never writes there in
practice, only `03-rrd-volumes.sh` does. `centreon-broker` now depends on
`fpm: condition: service_started` (not `service_healthy`, which would
wait for the whole install to finish) instead of the removed init
service. **Verified for real, fresh install**: `docker compose exec
centreon-broker id` → `uid=902(centreon-broker)` throughout, both RRD
directories owned `centreon-broker:centreon-broker`, all 7 services
healthy, login `200`.

**Log-streaming bug fixed upstream, confirmed (2026-10-01, `aed9c18025`,
centreon-collect PR #3823).** `log-tail.sh` had copied the
`/proc/1/fd/1` pattern from its `centreon-engine` sibling script without
needing it: it's backgrounded directly from `container.sh` via a plain
`&`, no stdout redirection in between, so it already inherits the
container's real stdout through normal parent→child fd inheritance —
reaching for `/proc/1/fd/1` explicitly added a cross-process `/proc`
access with no benefit, and broke once PID 1 became the `api_control.py`
wrapper instead of `cbd` directly. First pull after the report
(`2026-10-01T14:47:43Z`) still showed the bug — turned out to be CI lag,
that build had failed on unrelated C++ unit tests and never actually
shipped the fix. Re-pulled after central-gorgone's next green CI run
(image `2026-10-01T15:30:12Z`): confirmed fixed for real, not just
trusted — `docker compose logs centreon-broker` now shows full real
activity past the startup banner (protocol registrations, multiplexing,
`central-module-master` connecting), no permission error. No full
reinstall needed to verify, just `docker compose pull centreon-broker &&
up -d --force-recreate centreon-broker` (the config volume was already
populated).

### RRD merged into `central-broker-master` (no separate `central-rrd-master`)

Per Fabien's call, the RRD role runs in the *same* `centreon-broker` process
instead of a separate container/localhost:5670 hop — `12-install.sh` patches
`cfg_centreonbroker_info` right after `insertBaseConf.php` (raw SQL, no UI
toggle exists for this — confirmed by searching
`www/include/configuration/configCentreonBroker/`): deletes the default
BBDO-forward-to-`localhost:5670` output block and replaces it with a real
`type=rrd` output (`metrics_path`/`status_path` =
`/var/lib/centreon/{metrics,status}/`, fields confirmed against
`centreon-collect/broker/rrd/src/factory.cc`, not guessed).

**Corrected 2026-09-29, twice.** The first version matched the block to
delete by a hardcoded `config_group_id = 1`, assuming `insertBaseConf.sql`'s
own 0-indexed group numbers (0=unified_sql, 1=legacy-rrd-forward,
2=event_script) hold at runtime. A live install was later observed with
`unified_sql` itself sitting at `config_group_id = 1` — deleting by number
risked silently clobbering the wrong output block. Fixed to match by
content instead: the legacy default is the only "output" row for this
config with a `port = 5670` key at all, and the replacement's own group id
is computed as one past whatever the current max already is, so it can
never collide no matter how the database got there — verified against a
simulated fresh-install seed (isolated scratch table, not the real
database) to confirm this. Separately, the field list was missing
`insert_in_index_data`, `store_in_data_bin`, `rrd_cached`, and
`rrd_cached_option`: Fabien recreated the output through the UI to get a
genuinely functional one and asked for the real values to be captured here,
taken directly off that live, working database rather than re-guessed.

**Corrected 2026-09-30 — the `@rrdtool_dir@`/`db_host` claim below was wrong.**
Both `options.rrdtool_path_bin` and `cfg_centreonbroker_info`'s `db_host` (for
`central-broker-master`, `config_id=1`) used to get a manual `UPDATE` in
`12-install.sh` right after `insertBaseConf.php`, on the assumption that only
the legacy web install *wizard* resolves `@rrdtool_dir@`/`@address@`
placeholders and the CLI/JSON-driven install never does. That assumption was
never actually tested and turned out to be false. Read the real install code
instead of re-guessing (`src/CentreonLegacy/Core/Install/Step/AbstractStep::
getBaseConfiguration()`/`getDatabaseConfiguration()`, `src/CentreonLegacy/
Core/Utils/Utils::executeSqlFile()`/`replaceMacros()`): every `.sql` file run
through `executeSqlFile()` (which includes `insertBaseConf.sql` *and*
`var/baseconf/centreon-{engine,broker}.sql`, all called from
`insertBaseConf.php`) gets every `@key@` placeholder replaced by
`$macros[key]`, and `$macros` is just the JSON answer-file fixtures
(`configuration.json`, `database.json`, ...) merged together — no wizard
involved at any point, plain array lookup. Both manual patches were reimplementing,
by hand, exactly what the fixtures already deliver automatically:
`configuration.json` already ships `"rrdtool_dir": "/usr/bin/rrdtool"`, and
`database.json`'s `address` field is already `sed`'d from `localhost` to
`${MYSQL_HOST}` (line 43, unrelated to and unaffected by this fix) before
`insertBaseConf.php` even runs. Verified empirically, not just by re-reading
code: commented out each manual `UPDATE` in turn, forced a genuinely fresh
install (`docker compose down -v && up -d --build`), and queried the DB
directly — `options.rrdtool_path_bin` came back `/usr/bin/rrdtool` and
`cfg_centreonbroker_info`'s `db_host` (config_id=1) came back `db` (the real
`MYSQL_HOST` for this compose stack), with no manual patch applied either
time. Both `UPDATE`s are now removed from `12-install.sh` for good. The
`GRANT ALL ON *.* to ...@'%'` statement that used to sit next to the
`db_host` patch is **not** part of this cleanup and stays — it grants the
`centreon` DB user itself remote access from any host, which nothing in the
real install mechanism does (a real VM install has DB and web co-located, no
equivalent need), so it's a genuine Docker-topology necessity, not a
redundant reimplementation.

This does **not** touch the RRD-merge block or the `central-module-master`
host fix right below it in `12-install.sh` — those patch DB rows to values
the real production mechanism has no way to produce at all (there's no
"merge RRD into central-broker-master" toggle anywhere in the UI or install
fixtures, and `central-module-master`'s `host=localhost` default is
*correct* for a monolithic/VM install — the split topology is what's
different, not the install mechanism), so "does the real mechanism already
handle this" doesn't apply to them the way it did here.

New shared volumes: `centreon-status`/`centreon-metrics`
(`/var/lib/centreon/{status,metrics}`), read-write on `centreon-broker`
(directories created/chowned in its own entrypoint wrapper, image doesn't
bake them), **read-only** on `fpm` — confirmed by tracing every `.rrd`
access in `www/`: `centreonGraph*.class.php` only ever shells out to
`rrdtool graph -` (`proc_open`) to read graph data, no write/unlink call
anywhere. `httpd` needs neither (serves no PHP). `rrdtool` added to the
`fpm` Dockerfile (`php:8.4-fpm` doesn't ship it).

Verified for real, both mechanism and real generated file: a hand-constructed
config with the merged rrd output made `centreon-broker` log `RRD: module
for Centreon Broker 26.11.0`, `RRD: using rrdtool 1.7.2`, and `centengine`
stayed connected. A real "Generate Configuration Files" + "Move Export
Files" click, against the corrected DB rows, produces the same shape on its
own, no hand-construction needed.

### Central routed through gorgone instead of local `copy()`

Central's `nagios_server` row ships `localhost = 1` by default
(`www/install/var/baseconf/centreon-engine.sql`), which sends "Generate
Configuration Files"/"Move Export Files" down `moveFiles.php`'s
`localhost==1` branch: a raw `copy()` straight into `cfg_dir`/
`/etc/centreon-broker`, directories that don't exist in `fpm` on purpose in
this split topology (no local engine, no local broker). `12-install.sh` now
flips it to `0` right after `insertBaseConf.php` (must stay after that
script's own `localhost = '1'` read, used once to seed `platform_topology` —
flipping earlier breaks a fresh install outright), which sends that same
click down the `else` branch instead: `centcore.cmd` for gorgone's
`legacycmd` to pick up. Confirmed working end to end against this stack's
`gorgone-central` (Pilier 2): a real "Generate Configuration Files" +
"Move Export Files" click produces a genuine `REMOTECOPY` of
`/etc/centreon-broker/`, tagged with `legacycmd`'s own
`{"centcore_proxy":1,"centcore_cmd":"RELOAD"}` metadata shape, landing on
`gorgone-central-poller` and getting picked up by the real `centreon-broker`
container.

`localhost` isn't just a "deliver locally vs. remotely" toggle though, it's
read throughout the codebase as "which `nagios_server` row IS Central" — read
directly, not guessed, before flipping it. Confirmed fallout for this stack,
none of it hit by anything this stack's own tests actually exercise:
`DbalBrokerConfigurationRepository::getBbdoServerAuthorizationToken()`
throws `"No BBDO server authorization token found on the central broker"` if
a new broker configuration is ever created through the modern API (its own
`WHERE ns.localhost = '1'` join then matches nothing); Meta Services stop
being generated at all rather than being rerouted
(`config-generate/generate.class.php`, `host.class.php` gate on
`localhost == 1`); `CentreonBroker::reload()`'s `ORDER BY localhost DESC`
pick can silently select the wrong server's reload command once no row is
`'1'`; `configServers/DB-Func.php`'s `defineLocalPollerToDefault()` becomes a
no-op. Left as a known trade-off of this dev/test stack rather than worked
around, since working around all of them would mean auditing every read of
that column across `www/` and `src/`, a much bigger change than this pass
was scoped for.

Two separate, pre-existing bugs surfaced while testing this, unrelated to the
`localhost` flag itself, worth tracking on their own: `formServers.php`'s
save handler calls `triggerGorgoneNodesSyncIfPullwss()`
(`configServers/DB-Func.php:1143`), which hits `http://127.0.0.1:8085/...`,
the wrong host from inside a container, still unfixed; and the newer REST
`configuration_monitoring_server_generate_and_reload_all` endpoint's
self-HTTP-call, fixed below (`MonitoringServerConfigurationRepositoryApi`).

### Automated config delivery for `centreon-broker`/`centengine` (2026-10-01)

Until now, the gorgone delivery path above only ran when a human actually
clicked "Generate Configuration Files" + "Move Export Files" in the legacy
UI — nothing in `12-install.sh` triggered it. That became a real problem,
not just a convenience gap: central-gorgone's own `centreon-broker` image
started gaining a fingerprint guard (MON-210954/PR #3823) that refuses to
start at all if the config it's handed still matches the package default
— and this stack's `poller-broker-init` seeds exactly that default into
the shared `poller-broker` volume before any real config ever lands, on
every fresh install. Without automating delivery, `centreon-broker` would
crash-loop on every `docker compose down -v && up` until someone manually
clicked through the UI.

Fixed with a new background script,
`container.d/13-generate-broker-config_background.sh` (+
`generate-broker-configuration.php`), following the same `*_background*`
convention as the log-tailer: retries `GET
/centreon/api/latest/configuration/monitoring-servers/1/generate-and-reload`
(login first, `X-AUTH-TOKEN` from the response) every 10s, forever, until
it returns `204`. One call does generate+move+reload for the poller in one
shot (`Centreon\Domain\MonitoringServer\UseCase\GenerateConfiguration`
internally calls `generateConfiguration()` then `moveExportFiles()`), so
it benefits **both** `centreon-broker` and `centengine` — no separate
automation needed per component. The earlier "self-HTTP-call can't work
from fpm alone" concern (see above) doesn't apply here: it was already
fixed by this project's own `CENTREON_INTERNAL_API_BASE_URL` wiring.

Deliberately NOT a direct in-process call to the `GenerateConfiguration`
use case (which would avoid the HTTP round-trip entirely, matching
`runPendingUpdates.php`'s pattern): investigated and ruled out —
`GenerateConfiguration::execute()` requires an authenticated admin
`ContactInterface`, only populated by a real HTTP request's auth listener;
faking one from a CLI context would mean reimplementing that listener's
logic, fragile and untested. Plain HTTP instead, kept dependency-free:
`allow_url_fopen` is `Off` in this image's `php.ini`
(`centreon.ini.template`), overridden per-invocation with `php -d
allow_url_fopen=On` rather than adding a `curl` apt package or the PHP
`curl` extension for one script.

No `depends_on` wiring between `fpm` and `gorgone-central`/
`gorgone-central-poller` — deliberately: would create a cycle
(`fpm` → `gorgone-central-poller` → `httpd` → `fpm`, since the poller
waits on `httpd` and `httpd` waits on `fpm`). Retrying the actual delivery
call itself, instead of trying to probe gorgone's connection state from
`fpm`, sidesteps that cleanly. Idempotent and safe to run on every
container start: each attempt only regenerates+redelivers from the
current DB state, the same as clicking the UI buttons twice.

**Verified for real**: fresh `docker compose down -v && up -d --build` —
`fpm` logs show one failed attempt (`HTTP 0`, httpd not up yet) then
`Configuration generated and delivered for poller 1 (HTTP 204)` ~10s
later; `centreon-broker`'s `/etc/centreon-broker/central-broker.json` has
the real delivered config (`db_host: db`, real encrypted `db_password`,
not the package default); `centengine`'s log reaches `"Poller
'central-broker-master' with id 1 connected"`. All 7 services healthy,
login still works.

**Full-stack blocker found and fixed (C9/C12, 2026-11-02)**: once
`gorgone-central`/`gorgone-central-poller` and C10's `moveFiles.php` fix were
all in the stack together, delivery still silently failed end to end despite
the API returning `204` — `centreon-broker`/`centengine` kept the
package-default config, and `gorgone-central-poller`'s pullwss connection
was rejected with `{"code":500,"message":"please registernodes"}` on every
retry. Root cause: `gorgone-central`'s image (pinned past MON-213814) now
fails fast at startup unless `GORGONE_API_USERNAME`/`GORGONE_API_PASSWORD`
are set (`10-create-config.sh`'s own `:?`-guarded vars) — a **different**
pair from `CENTREON_API_USERNAME`/`CENTREON_API_PASSWORD` already on this
service (that one is gorgone calling *out* to Centreon's web API; this one
is gorgone's own inbound API auth, which is what `fpm`'s identically-named
`GORGONE_API_USERNAME`/`GORGONE_API_PASSWORD` env vars authenticate
against). Without it, `gorgone-central` never started at all, so its `nodes`
module never loaded `nagios_server` into memory, and `proxy/httpserver`'s
pullwss handler had nothing to match the poller's `REGISTERNODES` id
against — the API still answered `204` because that endpoint only confirms
the HTTP call queued, not that gorgone ever received/applied it. Added the
missing pair to `gorgone-central`'s `environment:` (same value as `fpm`'s).
**Verified for real**, full stack rebuilt with `GORGONE_IMAGE`/
`BROKER_IMAGE`/`ENGINE_IMAGE` actually pinned to their `dependencies.env`
tags (not the Dockerfile/compose `:develop` defaults a plain local
`docker compose up` silently falls back to): `gorgone-central-poller`'s own
log shows real `REMOTECOPY` chunks landing (`Copy to '/etc/centreon-broker/'
finished successfully`), `central-broker.json`/`central-module.json` on disk
are freshly written (not the image's stale package default), `centengine`'s
log reaches `"Poller 'central-module-master' with id ... connected"`,
`centreon-broker` shows the same real BBDO connection, and all 9 services
(`db`/`fpm`/`httpd`/`centreon-cron`/`gorgone-central`/
`gorgone-central-poller`/`centreon-broker`/`centengine`/`rrdcached`) reach
`healthy`/`Up` with no restart loop. `rrdcached` itself confirmed in use:
`central-broker.json`'s rrd output carries `"host": "rrdcached"` as
delivered, and `rrdcached` shows active TCP connections from `centreon-broker`.

### PHP session persistence

`session.save_path` (`/var/lib/php/sessions`) wasn't a volume originally —
every `fpm` recreate silently logged every user out. The DB-side `session`
table (`CentreonSession::checkSession()`) only tracks whether a session id
is still valid, it never holds the actual `$_SESSION` content
(`$_SESSION['centreon']`, the hydrated contact object used throughout the
legacy pages) — that lives only wherever PHP's session handler stores it. A
plain `docker compose restart fpm` already survived this (container
filesystem untouched by a restart), only a recreate needed persistence.

**DESIGN CHANGE (MON-214095)**: a `centreon-sessions` named volume (files
handler) fixed this at first, but a shared RWX volume is exactly what keeps
`fpm` from scaling to replicas — it's the only thing in this image that
still needs one. Replaced with sessions in Valkey (the `valkey` service, a
BSD-licensed Redis-protocol-compatible store — avoids Redis's own RSAL/SSPL/
AGPL licensing entirely) via `phpredis` (`pecl install redis`, same pattern
`gnupg` already used). Env-driven, `files` by default so nothing changes
when unset:

- `PHP_SESSION_HANDLER=files|redis` (image default: `files`).
- `PHP_SESSION_SAVE_PATH`: the base DSN with **no credentials in it** (e.g.
  `tcp://valkey:6379`), required when `PHP_SESSION_HANDLER=redis`.
- `PHP_SESSION_REDIS_PASSWORD`: a **separate** env var, not embedded in
  `PHP_SESSION_SAVE_PATH` — `01-config.sh` `rawurlencode()`s it itself and
  appends `?auth=<encoded>` (or `&auth=...` if the save path already has a
  `?`). This means neither this compose file nor the Helm chart's Secret
  template has to pre-encode a password that might contain URL-unsafe
  characters — picked after k8s-helm flagged the chart builds this DSN from
  a Secret and needs that guarantee.
- `redis.session.locking_enabled=1` is always set (harmless when the
  `files` handler is active, only read when `redis` is): phpredis does
  **not** lock sessions by default, unlike the `files` handler — without
  it, two concurrent AJAX requests on the same session silently overwrite
  each other's `$_SESSION`.
- Session TTL is still just `session.gc_maxlifetime` (7200, unchanged) —
  Valkey expires the key itself, no file GC needed.

All of this is resolved into `/usr/local/etc/php/conf.d/centreon.ini` by
`01-config.sh` (same `envsubst` mechanism `PHP_TIMEZONE` already used), not
baked into the image. `centreon-pool.conf`'s own `php_value[session.*]`
lines were removed — a pool-level `php_value` always wins over a php.ini
setting, so leaving them would have silently overridden whatever
`01-config.sh` resolved.

### UI-uploaded media (`www/img/media/`)

Confirmed broken (Fabien caught it): the company logo, host/service icons,
and anything `centreon-pp-manager` auto-downloads under `img/media/ppm/`
are written by `fpm` straight into `www/img/media/`
(`FileWriteMediaRepository`, no dedicated upload dir outside the docroot) —
but `httpd`'s own `www/` is a build-time snapshot with zero shared volume
by design (Pilier 1's whole point). Anything uploaded after the image was
built is invisible to `httpd`: broken image icon in the browser, 404 under
the hood. Confirmed directly by comparing `www/img/media/` on both
containers — `httpd` only had the two placeholder files
(`.htaccess`/`.keep`) checked into git, `fpm` had real `.png` files
`centreon-pp-manager` had already downloaded.

Fixed the same way the RRD data volumes already are (`centreon-status`/
`centreon-metrics` above) — a narrow, dedicated volume for just this one
runtime-written path rather than sharing all of `www/`: new
`centreon-media` volume, mounted read-write on `fpm` (the writer) and
read-only on `httpd` (only ever needs to serve it back). Verified for real,
not just reasoned about: wrote a marker file as `www-data` on `fpm`,
fetched it back over real HTTP through `httpd` immediately — `200`, content
matched, no propagation delay.

### Backward compatibility of the `is_default`/`InternalApiClient` code fixes with a classic VM install

Both `common-Func.php`'s `printDebug()` fix and
`MonitoringServerConfigurationRepositoryApi`'s fix touch real application
code shipped to every install, VM/RPM/DEB included, not just this Docker
image — checked explicitly, not assumed, since a fix that only helps Docker
while quietly breaking existing VM behavior would be worse than the bug.

**`is_default` fallback.** On a fresh install, `localhost=1` and
`is_default=1` are seeded together for Central, same `INSERT`
(`www/install/var/baseconf/centreon-engine.sql`) — the fix is a no-op there.
On an **upgraded** VM, `is_default` is not a new column needing a backfill:
it predates the entire supported upgrade window (added 2011, seeded since
2012), and any install that has passed through `Update-2.8.20.php` (2018,
essentially every live install today) already had it backfilled once via
`UPDATE nagios_server SET is_default = 1 WHERE localhost = '1'` if no row
had it set. The one real gap, pre-existing and not introduced by this fix:
`deleteServerInDB()` (`configServers/DB-Func.php`) never reassigns
`is_default` when the currently-default poller is deleted, and the only
self-heal (`defineLocalPollerToDefault()`) runs solely on the next poller
add/edit form save, not on load — a VM admin who deletes the
default-flagged poller and never touches the servers form again could see
`is_default` empty everywhere. `printDebug()`'s query now falls back to the
old `localhost = '1'` behavior in that exact case, matching the same
fallback chain `Centreon_Object_Instance::getDefaultInstance()`
(`lib/Centreon/Object/Instance/Instance.php`) already uses for the identical
"is_default, else localhost" lookup — not a new convention invented for
Docker, extending an existing one.

**`InternalApiClient::convertToLocalUrl()` fallback.** `CENTREON_INTERNAL_API_BASE_URL`
is empty by default on a VM (nothing sets it outside this split compose
file). With it empty, `convertToLocalUrl()` falls back to
`$_SERVER['SERVER_ADDR']` plus the real `$_SERVER['SERVER_PORT']` — the
server's own internal listen address, never whatever host/IP an external
client used to reach it, so a monolithic VM needs zero configuration change.
This is also a strict improvement over the old behavior for that case, not
just a neutral no-op: the old code used `$_SERVER['SERVER_NAME']` alone,
silently dropping the port (defaulting to 80), which was already latently
broken for any VM running Apache on a non-standard port; the new fallback
path uses the real port.

### Log redirection to stdout

**Corrected 2026-09-30 — the original symlink approach never actually
worked for PHP.** `/var/log/centreon/*.log` used to be symlinks to
`/dev/stdout`, created at build time, on the assumption this was safe for
every Monolog `stream` handler in `prod`
(`config.new/packages/monolog.yaml`: `web_file` plus the per-channel
authentication/token/password/upgrade/plugin-pack-manager/poller-install
handlers) and `rrdtool.log` (`centreonGraph*.class.php`'s `proc_open()`
stderr descriptor) — reasoned about, but only ever verified with a shell
`>` redirect, never with the actual PHP code path. Found empirically while
testing the schema-upgrade mechanism below: `LoggerUpgrade` logged
`"The stream or file '/var/log/centreon/prod.upgrade.log' could not be
opened in append mode: Failed to open stream: No such file or directory"`
for every single write, despite the symlink existing and pointing
correctly. Root-caused directly, not guessed: PHP's own `fopen('/dev/stdout',
'a')` fails with that exact error for *any* of these files (confirmed with
a throwaway script, not just `prod.upgrade.log`), and `proc_open()`'s file
descriptor spec hits the identical failure for `rrdtool.log` — an
append-mode open of a pipe by path behaves differently from userland PHP
than a shell `>` redirect does, which is exactly why the original
shell-only smoke test looked fine. `/proc/1/fd/1` (the container's actual
PID 1, already used by this same Dockerfile's `*_background*` convention)
isn't a fix either: `www-data` (php-fpm's own workers) gets a "Permission
denied" there instead, since it belongs to PID 1's owner, not the worker's.

**Fixed properly**: these 8 files stay real on-disk files (pre-`touch`ed at
build time so nothing has to wait for them to appear), and a new
`container.d/02-log-tail_background.sh` — using this Dockerfile's own
`*_background*` convention (`container.sh` already backgrounds and
redirects these to `/proc/1/fd/1` itself, running as root before any
privilege drop, so no PHP `fopen()` is involved for the tail process
itself) — runs `tail -F -q` across all 8 to stream them to the container's
real stdout. Verified for real, twice: (1) after the fix, `docker compose
logs fpm` shows actual structured Monolog output (real authentication
events with full JSON context, not just shell `echo` lines from
`12-install.sh` — which is *all* the original approach was ever shown to
produce), and (2) the schema-upgrade test below now runs start to finish
with zero warnings of any kind, where it previously logged one
"could not be opened" line per upgrade step.

**`login.log` stays deliberately separate**, same reasoning as before but
now shared by all 8 of the others too: `LoggerAuthentication.php` writes it
with `file_put_contents(..., FILE_APPEND | LOCK_EX)`, specifically so
external tools (fail2ban) can tail a real file on disk — its own comment
says so ("external consumers that watch /var/log/centreon/login.log"). Not
tailed to stdout here, so that integration point stays exactly as it was —
verified a real login still lands a correct line in `login.log` directly.

### Schema upgrades (new image version against an already-installed DB)

Until 2026-09-30, `12-install.sh`'s `ALREADY_INSTALLED` branch only created the
DB user and moved on — a newer image deployed against an older, persisted DB
never migrated anything, so newer PHP/JS code would run against a stale
schema. There is no existing non-interactive entry point for this anywhere in
Centreon (confirmed by reading the codebase, not assumed): the RPM/DEB
postinstall's own `upgrade` action never runs schema migrations either — the
real trigger is a human visiting the web UI after a package upgrade and being
routed through `www/install/step_upgrade/` by hand, once.

Added the missing piece as real, reusable product code rather than a
Docker-only shim: **`centreon/www/install/steps/process/runPendingUpdates.php`**,
a plain CLI script (same convention as `insertBaseConf.php` — no HTTP session
needed) that reuses the exact same mechanism the browser wizard drives
(`Core\Platform\Application\Repository\{ReadVersionRepositoryInterface,
ReadUpdateRepositoryInterface, WriteUpdateRepositoryInterface,
UpdateLockerRepositoryInterface}`, `App\Kernel::createForWeb()`): reads the
DB's recorded version, finds every `Update-<version>.php`/`.post.php` +
`.sql` file newer than it, runs them in order under a lock, then syncs
internal widget versions the same way `process_step5.php`'s post-update phase
does. `12-install.sh` calls it unconditionally right after the
fresh-install/already-installed branch (before `generationCache.php`) — no
new env var, since `findOrderedAvailableUpdates()` is naturally a no-op both
right after a fresh install (`informations.version` is already seeded to the
codebase's current version by `insertBaseConf.sql`) and on a plain recreate
against an already-current DB.

**Verified for real, including a genuine failure path**, not just the happy
path: forced `informations.version` back to `24.10.0` on a live DB and
recreated `fpm` — the chain correctly ran 8 real version steps
(24.10.0 → 24.11.0 → 24.11.1 → 25.01.0 → 25.01.1 → 25.01.2 → 25.03.0 →
25.03.1 → 25.05.0) against the real `Update-*.php` files before hitting a
`jwt_tokens` table collision — expected, since this test DB's *schema* was
never actually 24.10.0-shaped (it was built by the fresh-install path, i.e.
already fully current; only the version *marker* was rolled back by hand) —
not a bug in the mechanism. What mattered was confirmed: the failure was
loud (uncaught exception, non-zero exit under the script's own `set -eu`)
and the `fpm` container stopped entirely rather than limping on in a
half-migrated state. Restoring the version marker and recreating `fpm`
recovered cleanly, login regression-checked throughout.

**Known, accepted gap for this first pass**: the SQL resume cursor
(`DbWriteUpdateRepository::runSqlFile()` writes progress to
`install/tmp/<file>`) does not survive a mid-migration crash, since
`www/install/` lives in the image layer, not a persisted volume — a retry
after a crash re-runs the failed file from its first statement rather than
resuming. Accepted deliberately (Fabien's call) rather than adding a new
volume just for this: a real image upgrade is a rare event, not the hot
path, and the failure mode is a loud stop requiring manual DB inspection,
not silent corruption.

**Validated end to end against a genuinely period-accurate schema, not
just a faked version marker (2026-09-30).** The two tests above prove the
mechanism works and fails safely, but both start from *this codebase's own*
schema (always fully current) with only the `informations.version` row
faked — real, but not a true historical replay. Ran one: pulled the real
published `docker.centreon.com/centreon/centreon-web-alma9:dev-25.10.x`
monolithic image, pointed it at a fresh, empty MariaDB (same network, no
shared state with this stack's own `db`) to let it do a completely real
25.10.18 install — genuine period-shaped schema, not a guess. Stopped that
container, pointed this repo's own `fpm` image (already built, `RUN_INSTALL=true`)
at that same database instead. The full chain ran for real: every version
step from 25.10.18 up to `26.09.1` (the latest `Update-*.php` file that
actually exists — `26.11.0` is a fresh-install-only baseline,
`insertBaseConf.sql`, not reachable via an incremental update file, so
stopping at `26.09.1` while running `26.11.0` code is the correct, expected
outcome here, not a gap) executed cleanly, and — significant on its own —
all 4 T0 baked-in modules installed successfully for the first time on a
genuinely upgraded system (not a fresh 26.11 install where they'd already
be pre-registered), confirming the `license.d` fix from the section above
holds on a real upgrade path too. Cleaned up afterward (temporary containers
removed, this stack's own `db` never touched).

### T0 baked-in modules (Autodiscovery, License Manager, IT Edition, PP Manager)

**Fetched via `apt-get download` against Centreon's real signed apt repo
(2026-10-01, SEC-09 fix)**, not plain `curl`: the `modules_fetcher` stage
imports `apt-key.centreon.com`'s GPG key, adds
`packages.centreon.com/standard/apt-unstable` (`trixie-26.10-unstable`) as a
source, then `apt-get download pkg=version` for each of the 5 packages
(4 modules + `php8.4-sourceguardian-loader`). This gives the full apt
GPG+hash verification chain apt already does for every other package in this
Dockerfile (same precedent as `centreon-web/trixie/Dockerfile.dependencies`),
closing the gap a prior `curl`-without-checksum version had (flagged by the
`dockerfile-review` skill as SEC-09). Still pinned to exact versions via
`ARG`s, so Docker's layer cache still skips the whole stage on an unrelated
rebuild unless one of those `ARG`s is bumped. `dpkg-deb -x` extracts each
package's `usr/share/centreon/{bin,config,src,www}` straight into
`app_assembler`'s tree, right after the existing macro-replacement step —
already fully resolved by Centreon's own package build, nothing left to
substitute — so the httpd/fpm partition split downstream picks up module
static assets the same way it does for core `www/` files, no module-specific
logic needed.

**These are SourceGuardian-encoded, not plain PHP — a real missing PHP
extension, not just a licensing formality.** Every one of the 4 declares
`Pre-Depends: php8.4-cli, php8.4-sourceguardian-loader` and
`License: Proprietary` (confirmed by extracting and reading each `.deb`
directly). Fetched and wired in a 5th package from the same apt repo,
`php8.4-sourceguardian-loader` (`pool/main/p/`) — its `ixed.8.4.lin` shared
object copied to `/usr/local/lib/php-sourceguardian/` with a custom
`zend_extension = <full path>` ini, rather than the package's own
Debian-convention ini (bare `zend_extension = ixed.8.4.lin`, resolved
against `extension_dir` — the official `php:8.4-fpm` image doesn't use
Debian's `/etc/php/8.4/...` layout, so a full path avoids depending on this
base image's own `extension_dir` naming). Verified working: `php -v`'s
banner shows `with SourceGuardian v17.0.0`.

**Known, accepted risk, not silently assumed fine**: every module's
`control` file declares `Depends: centreon-web (>= 26.10~) (<< 26.11~)`, but
this codebase seeds `26.11.0` (`insertBaseConf.sql`) — apt-unstable has no
`26.11.x` build of any of these 4 yet. Extracting file trees directly (not
`dpkg -i`) never enforces this at build time, but the compatibility risk it's
warning about (module code built/tested against 26.10.x internals) is real;
revisit once a matching build appears.

**Each module needs its own one-time DB registration**, same mechanism as
clicking "Install" on the web UI's "Manage Modules" page:
`CentreonModule\Infrastructure\Service\CentreonModuleService::install()`,
driven by a new CLI script (same convention as `insertBaseConf.php`),
`centreon/www/install/steps/process/installBakedModules.php`, called
unconditionally from `12-install.sh` right after the schema-update step (no
new env var — each module's own `isInstalled()` check makes this a no-op
once registered). Install order matters:
`centreon-license-manager` → `centreon-pp-manager` →
{`centreon-autodiscovery-server`, `centreon-it-edition-extensions`} (each
module's own `Depends:` field). Note the autodiscovery id specifically: the
Debian **package** is `centreon-auto-discovery-server` (extra hyphen, used
for the apt path), but the actual `www/modules/<id>/` directory inside it —
what `CentreonModuleService` keys on — is `centreon-autodiscovery-server`
(no hyphen), confirmed by listing the extracted image directly after the
first naming assumption caused a real (quickly caught) failure.

**All 4 modules now install cleanly — `centreon-autodiscovery-server`/
`centreon-it-edition-extensions` needed one missing directory, not a
license/subscription requirement.** Both used to fatal deep inside
`CentreonLicense\ServiceProvider` (fully SourceGuardian-encoded — even test
files ship encoded, nothing in that class tree is readable) with
`Cannot find license directory`, thrown from `ImpApiService->setLicensePath('')`
while `ModuleSource::getDetail()` queries license info for the module.
Several missing-directory guesses were tried and empirically ruled out first
(`/etc/centreon/license`, `/usr/share/centreon/license`,
`/var/lib/centreon/license` — no change each time). **Real cause, confirmed
against a live production install (Fabien checked)**: the actual path is
`/etc/centreon/license.d/` (note the `.d`), `centreon:centreon`, `775` — the
`fpm` Dockerfile now creates it in the same block as `/var/lib/centreon`.
Verified end to end on a genuinely fresh install: all 4 modules register in
`modules_informations`, no failures logged, all 7 services healthy, login
still works. `installBakedModules.php` still wraps each module in its own
`try`/`catch` regardless — one module's install-time failure shouldn't be
able to take the whole container down, kept as defensive resilience rather
than removed now that the known cause is fixed.

**License upload crashed the `fpm` worker with a silent 500 — missing `gnupg`
PHP extension, found by testing an actual license upload, not guessed.**
Uploading a real license zip through the License Manager UI produced an
HTTP 500 with a completely empty response body, and nothing at all in
either `fpm`'s or `httpd`'s logs — no exception, no Apache error, no
php-fpm worker-crash notice, consistent with a raw segfault rather than a
clean PHP error. Reproducing it directly in-process (bypassing the legacy
session auth that blocked a plain `curl` repro) surfaced the real cause:
`Error: Class "gnupg" not found`, thrown from
`CentreonLicense\Infrastructure\Service\LicenseService::getGpg()` while
verifying the PGP signature each `.license` file inside the zip carries
(`-----BEGIN PGP ...`) — a real missing PHP extension, unrelated to
SourceGuardian or the `license.d` directory fix above. `gnupg` is PECL-only
(never bundled in PHP's own source tree, so `docker-php-ext-install` can't
build it) — built via `pecl install gnupg` + `docker-php-ext-enable gnupg`
against this image's own PHP build rather than installing Debian's
`php8.4-gnupg` package, which targets a different compile of PHP 8.4 with
no ABI guarantee against the official `php:8.4-fpm` image (same reasoning
as the SourceGuardian loader). Needs `libgpgme-dev` as a build-time
dependency. Verified end to end: the same upload now returns a clean `200`
with a real, well-formed `{"status":...}` response (a proper
"Can not be installed" business message per license, not a crash) instead
of an empty 500 — the crash is fully resolved; whether "Can not be
installed" itself is expected for this specific test license is a separate,
unconfirmed question, not a Docker packaging bug.

**Deliberately not copied forward**: `centreon-auto-discovery-server`'s own
`etc/centreon-gorgone/config.d/41-autodiscovery.yaml` (belongs to a real
gorgone container, Pilier 2, not this web-split image) and `etc/logrotate.d/*`
from any of the 4 (this stack redirects everything to stdout, no local log
files to rotate).

### Dead-weight cleanup (2026-10-01)

Fabien noticed the `fpm` image (1.59GB at the time) was heavier than it
should be and asked for dead/never-called files to be dropped. Explored
directly rather than guessed: `docker history --no-trunc` shows the
`COPY --chown=www-data:www-data /usr/share/centreon /usr/share/centreon`
layer in `app_assembler` at 412MB — `backend_builder`'s own
`COPY centreon/ .` copies the *entire* source tree wholesale (everything
that `composer install` needs AND everything it doesn't), the same root
cause `www/front_src`/`www/widgets/src` were already dropped for in this
same macro-replacement block, just never extended to the rest of the tree.

**Confirmed zero-risk, build/dev-only residue, dropped** (`du -sh` on each,
not estimated): `cypress/` (61M, E2E suite), `tests/` (26M, PHPUnit),
`packages/` (25.4M — the frontend monorepo's own `ui`/`js-config`/
`ui-context` workspace packages: TS source plus their own Cypress/Jest/
Storybook, consumed only at frontend build time via pnpm workspace linking;
the compiled output already lands separately in `www/static` via
`frontend_builder`), `doc/` (2.6M), `project/` (0.48M — a release-cutting
script plus a second copy of the same decorative image already dropped from
the repo root), `centreon-github-wall.jpg` (0.4M), `pnpm-lock.yaml` (1.1M),
`phpstan.core.test.baseline.neon` (0.13M), and
`src/CentreonLicense/Tests/` (0.24M — the only `Tests/` directory anywhere
under `src/`, confirmed by a dedicated search rather than assumed to be the
only one). All of this lands in the same `RUN bash -e <<'EOF'` block that
already did the macro-replacement pass.

**Bigger win, a real decision not a cleanup**: every `*.js.map`/`*.css.map`
under `www/` — **106MB on their own**, bigger than every other removal
combined. Dropped with an explicit yes from Fabien (not assumed safe): they
exist to let a browser map a minified error back to real source, useful
only with an error-tracking tool (Sentry or similar) actually configured to
read them, which nothing in this stack does yet. Removed in a separate
`RUN find www -name '*.map' -delete` *after* the `COPY --from=modules_fetcher`
step, not in the same block as the rest — the 4 T0 modules' own source maps
only exist once that COPY has landed.

**Checked and deliberately left alone**, not just skipped: `vendor/` (67M)
has only ~1.9MB of `test`/`docs` directories inside the composer packages
themselves (`smarty/docs` 1.1M, several `symfony/*/Test` directories at a
few dozen KB each) — too little to justify the extra risk of deleting
inside `vendor/` (composer's optimized classmap can reference paths there).
`libinstall/` (168K) and `cron/` (156K) are legacy install/cron helper
scripts, small enough that the research cost of confirming nothing in the
UI still shells out to them on demand (e.g. a partitioning action) outweighs
the gain. The apt-get/`pecl install gnupg` layer in the `fpm` stage (260MB,
confirmed via `docker history`) is a real further optimization — a
build-time-vs-runtime dependency split (install `-dev` packages, compile,
purge, reinstall just the runtime `.so`s) — but changes Dockerfile build
mechanics rather than just deleting files, with real risk of breaking a
shared-library link if done carelessly; left for a separate pass.

**Verified for real**: `docker images` before/after — `fpm` dropped from
1.59GB to 1.26GB (−330MB, more than the ~223MB estimated from the file
sizes alone — the removal cascades favorably through the squashed final
layers), `httpd` from 379MB to 245MB (−134MB, inherits the same cleanup
since `www_httpd_subset` builds on top of `app_assembler`). Fresh install
afterward: all 7 services healthy, all 4 T0 modules still register
correctly, login still works, and a real module static asset
(`centreon-license-manager`'s `remoteEntry.b2c2abd3.js`) still serves `200`
— confirms the `.map` deletion didn't catch any `.js`/`.css` file by
accident.

### `dockerfile-review` skill findings applied (2026-10-01)

Full `dockerfile-review` pass + cross-tool check (`docker ai`, `droast`),
findings applied beyond the SEC-09 module-fetch fix documented above:

- **SEC-02**: `useradd` now passes `-l` for all 4 Centreon service accounts
  in the `fpm` stage (avoids a full-size `/var/lib/lastlog` entry per UID —
  cheap defensive habit, no functional change at these UID ranges).
- **OPT-06**: `COPY --from=<stage>` instructions without `--chown` now use
  `--link` (hardlink + parallel layer creation). Instances that already
  carry `--chown` (`www_httpd_subset`→`httpd`, `app_assembler`→`fpm`) were
  left as-is — `--chown`/`--link` are mutually exclusive.
- **SEC-05**: the 3 config-template `COPY`s (httpd's `centreon.conf.template`,
  fpm's `centreon.ini.template`/`centreon-pool.conf`) now carry
  `--chown=www-data:www-data` instead of landing root-owned.
- **FMT-01**: the httpd module-enable/log-symlink block and the fpm
  mkdir/touch/chown runtime-dirs block, previously long `&&`-chained single
  `RUN` lines, are now heredocs (`RUN bash -e <<'EOF' ... EOF`), matching
  every other multi-command `RUN` in this file.
- **FMT-02**: the two single-line file writes (`sourceguardian.ini`, the
  `centengine` debug shim) switched from `RUN echo`/`RUN printf` to `COPY
  <<EOF ... EOF` heredocs — content lives in the Dockerfile as a real
  block, not escaped inside a shell string, and the shim keeps its
  `--chmod=755` directly on the `COPY`.
- **SEC-01**: still no explicit `USER` in either final stage — this is
  intentional, not an oversight: httpd's master needs root to bind port 80
  and fork workers, php-fpm's master needs root to create/chown its own
  runtime state and to spawn workers as `www-data` per the pool's own `user
  = www-data` directive. Both already drop worker privilege internally,
  same reasoning the official `httpd`/`php:8.4-fpm` images rely on for not
  setting `USER` themselves. Documented inline in the Dockerfile right
  above each `ENTRYPOINT` rather than left unexplained.
- **COMP-09**: new build-time check in the `fpm` stage, right after the
  SourceGuardian ini is written — `php -m | grep gnupg` and `php -v | grep
  SourceGuardian`, failing the build if either didn't load. Both failure
  modes already happened for real this session (the gnupg license-upload
  crash, the SourceGuardian ini path) and were only caught by actually
  running the container — this turns them into a build-time failure
  instead.
- **OPT-13**: added a repo-root `.dockerignore`. Kept deliberately minimal
  (`.git`, editor/OS metadata, `.claude`) since this build context (repo
  root, `context: ../../..` in `docker-compose.yml`) is shared by several
  other Dockerfiles/CI workflows across the monorepo — excluding anything
  a sibling Dockerfile's `COPY` might rely on would be a cross-image
  regression, not a cleanup.

**Verified for real**: isolated `docker build --target fpm` passed
(COMP-09's own check gates this), then a full fresh-install regression
(`docker compose down -v && up -d --build`) — all 7 services healthy, all 4
T0 modules registered, login `200`, a static asset through `httpd` `200`,
`gnupg` present in `php -m` inside the running `fpm` container. Committed
`4c67312ea4`.

**FMT-03 (`4c67312ea4`'s own follow-up, `48197e8aba`): missed on the first
pass, caught by Fabien asking "il y avait plus de correction que ça à
faire non ?"** — a re-read of the full skill (not just this session's own
summarized recollection of it) found a real HIGH finding neither applied
nor reported: two `RUN` blocks contain a pipe (`|`) without `pipefail`,
which silently masks an upstream command's failure (the skill's own
documented incident class). One was pre-existing (the `find | xargs |
xargs` macro-replacement pipe in `app_assembler`), the other was introduced
by this same session's own COMP-09 fix (`php -m | grep`, `php -v | grep`).
Fixed differently for each, since blindly bolting on `pipefail` isn't
always correct: the COMP-09 block got `bash -eo pipefail` directly (safe —
`grep`'s exit-1 there genuinely means "extension missing", exactly what the
check wants to catch). The macro-replacement block did **not** get
`pipefail` — `grep -l` legitimately exits 1 on "no file matched", which is
an expected, non-error outcome on this block (not every build necessarily
has an unresolved macro left), and `pipefail` would have turned that
benign case into a hard build failure. Restructured instead to remove the
pipe entirely (each stage writes to an intermediate file under `/tmp`),
which still lets `bash -e` catch the real risk (`find`/`sed` genuinely
failing) without misreading `grep`'s normal "nothing to do" exit code.
Verified for real again: isolated `docker build --target fpm` passed, full
fresh-install regression — 7 services healthy, 4 T0 modules registered,
login `200`.

**Re-ran the skill properly end to end (2026-10-01, `2b05df3e0c`), per
Fabien's "relance le skill"** — full methodical line-by-line pass against
the current file (not recalled from memory) plus a fresh `droast` run.
Found and fixed two real items that had slipped through every earlier
pass: **SEC-05**, the `sourceguardian.ini` `COPY <<EOF` heredoc (added by
an earlier FMT-02 fix) never got `--chown=www-data:www-data` like the
other 3 config-template `COPY`s; **FMT-01**, `frontend_builder`'s
`corepack`/`pnpm` `RUN` block (4 commands chained with `&&`) was never
converted to heredoc — this stage had never been touched by any prior
dockerfile-review pass at all. `droast` confirmed no new regressions
(`DF041` multiple-HEALTHCHECK and the unpinned-version `DF005`s are the
same already-refuted/already-judged findings as before; `DF033`
"no .dockerignore" is a false positive from droast only seeing the
`trixie/` subdirectory, not the real build context at the repo root where
the `.dockerignore` actually lives). Verified for real: isolated
`docker build --target httpd`/`--target fpm` both passed, full
fresh-install regression — 7 services healthy, 4 T0 modules registered,
login `200`, static asset `200`.

Still open, not applied — found on the same full re-read, genuinely minor
or already-decided, not silently missed:
- **OPT-08** (MEDIUM): `httpd:2.4`/`php:8.4-fpm` are not slim/alpine
  variants. Not an oversight — this was an explicit, already-documented
  decision (see "Base image decision" in the project's own history):
  matches the Pillar-1 page's own validated manual smoke test, and
  switching to Alpine means a different libc (musl) and package manager,
  a real compatibility risk for the compiled PHP extensions this image
  already depends on (SourceGuardian's `.lin` loader, `pecl install
  gnupg`) — not something to change as a drive-by Dockerfile-review fix.
- **OPT-04** (LOW): no `apt.conf.d/99custom` heredoc globally disabling
  recommends/suggests. Redundant in practice — every `apt-get install` in
  this file already passes `--no-install-recommends` per-line (OPT-03,
  already compliant), so this would be a style preference, not a behavior
  change.
- **OPT-09** (LOW): no `ARG TARGETARCH`. Only matters for multi-arch
  builds; this image isn't built multi-arch today, nothing currently reads
  it.
- `COMP-05`/multi-target `HEALTHCHECK` cross-check false positive already
  refuted (see the SEC-09 entry's own review), and the build-vs-runtime
  `-dev` package split, already measured and found not worth it (~12.5MB,
  not the full 260MB layer) — see the project memory file for the numbers,
  not repeated here since it didn't change anything in this Dockerfile.

### "Add Poller" install script (`www/poller/install.sh`) (2026-10-05)

Fabien tested the real "Add Poller" one-click curl|bash flow against this
stack (`curl .../centreon/poller/install.sh | bash -s -- --poller_token
...`) and got the SPA's `index.html` back instead of a script — the request
fell through to the frontend catch-all because nothing served anything at
that path.

**Root cause, not guessed**: `install.sh` isn't a static source-tree file
or something nfpm templates at package time — it's *assembled* by
`centreon/install-poller/build.sh` (concatenates `main-head.sh` +
`lib/*.sh` + `src/*.sh` + `main-tail.sh`, `sed`-ing in the version) as its
own dedicated CI step (`.github/workflows/web.yml`), **before** packaging
even runs; nfpm then just copies the already-built output
(`packaging/src/centreon-web.yaml`: `src: ../install-poller/install.sh`,
`dst: /usr/share/centreon/www/poller/install.sh`, mode `0644`). Our
from-source build never ran this step at all. No Apache rewrite or
Symfony route is involved — it's served as a plain static file once it
exists.

Fixed in `backend_builder`: run `install-poller/build.sh` with explicit
`CENTREON_MAJOR_VERSION`/`CENTREON_MINOR_VERSION` build ARGs (defaults
`26.11`/`0`, matching the repo root's `.version`/`.version.centreon-web` —
not read automatically, since `COPY centreon/ .` only brings in the
`centreon/` subdirectory, not the monorepo root those files live at),
stability `unstable` (matches this dev/test stack's own apt suite choice
elsewhere, not `stable`). Then in `app_assembler`, a dedicated `COPY
--from=backend_builder` pulls just the generated `install.sh` into
`www/poller/install.sh` — same dst/mode as packaging — and the dead-weight
cleanup block now also removes `install-poller/`'s own build sources
(`main-head.sh`/`lib/`/`src/`/`build.sh`), which the wholesale `COPY
centreon/ .` in `backend_builder` otherwise drags along as unused weight,
same pattern as `cypress/`/`tests/`/etc.

**Second real bug found while testing this fix, not a separate issue**:
the first version used `COPY --chmod=644` directly on the single-file
destination `./www/poller/install.sh`, whose parent directory didn't
exist yet in the assembled tree. `--chmod` applies to a directory COPY
has to auto-create along the way too, not just the file being copied —
same underlying class of bug as SEC-10 in the `dockerfile-review` skill
(there, a directory-tree `COPY --chmod` clobbering a directory's own
mode; here, a single-file COPY's auto-created parent catching the same
fate). The result: `www/poller/` ended up `644` (no execute bit, directory
untraversable), and the real endpoint returned a `403` instead of the
script — progress over the original 404-via-SPA-catch-all, but still
broken, and only caught by actually curling the endpoint through httpd,
not by checking the file's own permissions in isolation. Fixed by
`mkdir -p www/poller` *before* the `COPY`, so it already exists with a
normal default mode and `--chmod=644` only ever touches the file.

**Verified for real, twice** (once per bug): a fresh
`docker compose down -v && up -d --build`, then
`curl http://localhost:18080/centreon/poller/install.sh` — first attempt
`403`, second (after the `mkdir -p` fix) `200` with the real script
content (`# Centreon poller installer - 26.11.0`, real body, not SPA
HTML). All 7 services healthy, login `200`. The actual poller-provisioning
flow (running the downloaded script against a real host) wasn't exercised
— out of scope for this fix, which only had to make the script itself
downloadable correctly.

## Scheduled jobs (`cron` image/service), 2026-10-06

A real RPM/DEB install gets `/etc/cron.d/{centreon,centstorage}` for free (the
`cronie`/`cron` package dependency, `packaging/centreon-web.yaml`) — 10 jobs:
CentACL cache rebuild and outdated-API-token removal (every minute), downtime
start/stop and knowledge-base sync (every 5 min), storage purge and DB
partitioning (daily), state-event/dashboard reporting builders and a DB
backup (daily, Perl). None of this was ever ported to this split image —
flagged by Fabien as "un détail qu'on a jamais géré".

**Dedicated image/container (`--target cron`, service `centreon-cron`), not
folded into `fpm`:** 3 of the 10 jobs are Perl (`eventReportBuilder`,
`dashboardBuilder`, `centreon-backup.pl`) — `fpm` has no Perl at all and has
no reason to — and a persistent scheduler daemon inside `fpm` would multiply
per replica if that image is ever scaled (the same class of problem
`INSTALL_DB_STEPS` exists to avoid for the install step, see the Helm chart
regression entry below). On k8s, these 10 jobs should become native
`CronJob` resources using this same image with `command` overriding
`cron -f` to invoke one script directly — no daemon needed there, the
cluster's own scheduler replaces cron entirely (flagged to the
"k8s helm charts construction" session for their chart).

**No shared volume with `fpm` for `/etc/centreon`, on purpose.** `fpm`'s own
`12-install.sh` short-circuits entirely ("Centreon already installed —
skipping install") the instant `/etc/centreon/centreon.conf.php` exists —
today that's harmless because `/etc/centreon` isn't a volume at all (every
`fpm` recreate starts with a clean container filesystem, so the guard is
always false in practice, and the idempotent topology-adaptation block
added for the Helm chart migration keeps re-running on every start as
intended). Sharing this path would flip that guard on the moment this new
container's first write landed on the volume, silently breaking that
already-validated idempotent behavior on the very next `fpm` recreate.
Instead, `entrypoint/cron/container.sh` generates its own **local** copy of
the two files the legacy PHP/Perl scripts read (`centreon.conf.php`,
`conf.pm`), straight from the same install-fixture JSON
(`www/install/tmp/database.json`) `fpm`'s own `12-install.sh` reads — same
values, independently derived, zero coupling to `fpm`'s own container state.

**Three real bugs found only by waiting for an actual scheduled run, not by
the container starting cleanly** — exactly the lesson this project keeps
re-learning about anything living in an entrypoint/background process:

1. **Debian's `cron` package's PAM session stack silently denies every job
   in a container**, with zero visible error anywhere (no syslog in this
   image, and cron's own libc `syslog()` calls for per-job logging go
   nowhere without one — cron itself stays alive, `ps aux` shows it running
   the whole time, every scheduled job just never executes). Diagnosed by
   temporarily standing up a minimal `/dev/log` receiver (`socat
   UNIX-RECVFROM:/dev/log,fork`) in the running container — manual testing
   via `docker exec` reproduces this identically (not a container.sh-only
   issue this time), so it was invisible until something actually inspected
   PAM's own session result, not just whether the command "ran". First
   tried loosening only `pam_loginuid.so` (the most commonly-cited Docker+
   cron cause) to `optional` — confirmed applied, jobs still silently never
   ran, so something else in the included `common-auth`/`common-account`/
   `common-session-noninteractive` chain was also denying the session.
   Replaced the whole `/etc/pam.d/cron` file with an always-succeed stack
   (`pam_permit.so` for auth/account/session) instead of chasing which
   exact included module was the real blocker — `/etc/cron.d`'s own per-line
   user column is already the actual access control for what each job can
   do, this file only gates whether cron is allowed to start a session at
   all.
2. **Job log files were `root:root` with plain `664`** (touched by
   `container.sh` running as root) — every job except the single
   `root`-owned backup line (`www-data`/`centreon`) failed to *open* its own
   `>> file` redirect ("Permission denied"), and that failure leaked back
   through cron's own stdout/stderr capture pipe instead of landing in the
   file, surfacing only as cron's generic "No MTA installed, discarding
   output" with zero indication of the real cause. Fixed by `chown
   centreon:centreon` (`www-data` is already in the `centreon` group, same
   cross-container setup `fpm` already has).
3. **No `[client] skip-ssl` mysql client config** (this stack's MariaDB
   runs `--skip-ssl`, same as `fpm`'s own fix for this) — manifested as a
   misleading `SQLSTATE[HY000] [1044] Access denied for user 'centreon'@'%'`
   from PDO, while the raw `mysql` CLI showed the real cause ("SSL is
   required, but the server does not support it"). Fixed by writing the
   same `/etc/mysql/conf.d/client-no-ssl.cnf` `fpm` already writes.

**Fourth gap, found the same way:** `centKnowledgeSynchronizer.php`'s DI
container bootstrap autoloads `CentreonAutoDiscovery\ServiceProvider` (a T0
module's SourceGuardian-encoded class) regardless of what that specific job
actually does — same shared legacy bootstrap as `fpm`. Fixed by copying the
same SourceGuardian loader (`modules_fetcher`'s `ixed.8.4.lin`) into the
`cron` image too. `gnupg` (`fpm`'s other PECL extension, for license
upload/verification) is deliberately **not** duplicated here — nothing in
the 10 cron jobs touches that code path.

**`centreon-perl-libs` (the package providing `centreon::script::*`, needed
by `eventReportBuilder`/`dashboardBuilder`) has a genuinely broken Debian
dependency, not something on this image's side:** its own sub-package
`centreon-perl-libs-common` declares `libcrypt-openssl-aes-perl` and
`libnet-curl-perl`, neither of which exist anywhere in Debian trixie's own
repos (confirmed directly: `apt-get install --dry-run` fails even with
`contrib`/`non-free`/`non-free-firmware` all enabled). Both are, however,
published on `packages.centreon.com/plugins/apt-stable` (the same repo
`centreon-plugins` itself depends on for its own SNMP/crypto features,
Fabien's own suggestion) — added as a second apt source, same signing key
as the `apt-unstable` repo already used for the T0 modules.

**Verified for real, end to end, against a fresh `down -v && up -d`
(8 services now, not 7):** all services healthy, login `200`, 4 T0 modules
registered, `centengine` connected to `centreon-broker` — none of this
regressed by adding the 8th container. Watched real scheduled firings (not
just a clean container start): the every-minute jobs (`centAcl.php`,
`outdated-token-removal.php`) and the every-5-minute one
(`centKnowledgeSynchronizer.php`) all produce clean output against the real
installed DB — zero bytes (silent success) for the first two, a single
expected informational line ("Wiki is not configured...") for the third.
The remaining daily/Perl jobs (`eventReportBuilder`, `dashboardBuilder`,
scheduled 3am/6am) weren't watched firing live (not waiting hours for a cron
schedule) — their package dependencies resolve cleanly and the binaries are
present and executable, but their actual runtime behavior against this
stack is unverified, flagged here rather than silently assumed fine.

**Two jobs dropped entirely, 2026-10-06, found by the "k8s helm charts
construction" session testing the chart's own CronJob port of this same
mechanism:**

- **`centreon-backup.pl`** — Fabien's call: this stack's persistence model
  is Docker volumes, not a filesystem backup written inside a container
  that gets torn down with `down -v` anyway. No `mkdir -p /var/cache/centreon`
  needed for this one, but while fixing the next bug the missing cache dir
  was found affecting this job's own category of script too.
- **`centreon-send-stats.php`** — real bug found first (`Unable to create
  the "cache" directory`, `_CENTREON_CACHEDIR_`/`/var/cache/centreon` isn't
  created anywhere in this image — fixed, `mkdir`+`chown centreon:centreon`+
  `chmod 775`, same directory fpm already bakes at build time but this
  image never shared). Fixing it surfaced a second, more important finding:
  **this script sends a base telemetry payload (UUID, versions, platform
  info, auth options) to `statistics.centreon.com` every night,
  *unconditionally*, for any non-remote central** — confirmed by reading
  `cron/centreon-send-stats.php:111-158` directly: `options.send_statistics`
  (already forced to `'0'` by `12-install.sh`, same privacy intent) only
  gates the *additional* per-module data via `getAdditionalData()`, never
  whether the base call happens at all. A disposable dev/test stack phoning
  home nightly contradicts that already-established intent, so the job
  itself is dropped from `/etc/cron.d/centreon` at container start
  (`sed -i '/centreon-send-stats\.php/d'`) rather than relying on a DB flag
  that doesn't actually suppress the call.

Both removals are `sed -i '/<script>\.{pl,php}/d' /etc/cron.d/centreon`
in `entrypoint/cron/container.sh`, applied after copying the real file —
the section headers stay (harmless comments), only the two job lines are
gone. Verified for real: `cat /etc/cron.d/centreon` after a fresh
container start shows both lines removed, the other 7 intact, cache dir
`drwxrwxr-x centreon:centreon`.

## Admin/gorgone credentials made configurable, 2026-10-06

Fabien asked for two things: a way to set the admin password via env var (or
force a change on first login), and clarity on when/how `centreon-gorgone`'s
own password gets configured (he'd noticed it's referenced by
`31-centreon-api.yaml` but didn't know the mechanism).

**Three new opt-in env vars, all off/unset by default (zero behavior change
unless explicitly set)**, implemented in `12-install.sh`:

- `ADMIN_PASSWORD` — if set, patches `admin.json`'s bcrypt hash before
  `insertBaseConf.php` runs (the file it reads as-is via
  `getAdminConfiguration()`), instead of the fixture default
  (`Centreon!2021`).
- `ADMIN_PASSWORD_EXPIRED=true` — backdates admin's own
  `contact_password.creation_date` by 181 days, past the platform's own
  default password-expiration policy (180 days,
  `password_expiration_delay=15552000`, already enabled for every user by
  `insertBaseConf.sql` — this doesn't introduce that policy, just makes
  admin's own row look old enough to already be caught by it). **Verified
  for real through the actual login UI** (not just the REST API, see
  below): logging in with the new password correctly triggers the
  password-reset prompt.
- `GORGONE_API_PASSWORD` (default `centreon-gorgone`, matching the
  contact's own alias) — see the real finding below.

**Real finding on `centreon-gorgone`'s password**: the product's own
mechanism for this (`getGorgoneApiCredentialMacros()`,
`www/install/steps/functions.php:301`) reads gorgone's own
`/etc/centreon-gorgone/config.d/31-centreon-api.yaml` **from fpm's own
filesystem** to reuse whatever password gorgone had already picked —
correct on a monolithic/VM install where gorgone is co-located, but fpm has
no `/etc/centreon-gorgone` at all in this split topology, so the read
always misses and the macro falls back to its own coded default: an empty
string. Confirmed directly: `contact_password.password` for contact_id=4
is an empty string on a stock fresh install of this stack — the dedicated
`centreon-gorgone` system contact (`contact_admin=1`,
`is_service_account=1`, created for exactly this kind of use, confirmed in
`insertBaseConf.sql`) was simply unusable.

Fixed on both sides, no cross-repo coordination needed: `12-install.sh` now
sets a real bcrypt-hashed password for contact_id=4 directly (via PDO with
a parameterized query — a bcrypt hash always contains literal `$`
characters, which would get silently mangled by any shell double-quoted
`-e`/heredoc re-expanding it as parameter substitution). And
`gorgone-central`'s own `CENTREON_API_USERNAME`/`CENTREON_API_PASSWORD` env
vars (docker-compose.yml) — already wired into gorgone-central's own
`12-central-config.sh` for its tpapi block, just previously hardcoded to
`admin`/`Centreon!2021` in our own compose file — now point at
`centreon-gorgone`/`${GORGONE_API_PASSWORD:-centreon-gorgone}` instead,
decoupling gorgone's own REST-API identity from whatever the admin
account's password happens to be. **Verified for real**: fresh install,
`gorgone-central`'s own generated `31-centreon-api.yaml` shows
`username: "centreon-gorgone"`, and its logs show a real `X-AUTH-TOKEN`
obtained from Centreon's login endpoint (confirms the credential actually
authenticates, not just that the file looks right) — the only remaining
401 in those logs is the already-known, unrelated autodiscovery license
limitation.

**REST API doesn't support the password-expired flow — a real product
limitation, found while testing, not a bug in this image.** `curl
.../login` with a correct-but-expired password returns a generic
`{"code":401,"message":"Authentication failed"}`, indistinguishable from a
wrong password — `PasswordExpiredException` is thrown distinctly deeper in
the stack (`LocalProvider::respectLocalSecurityPolicyOrFail()`,
`src/Security/Domain/Authentication/Model/LocalProvider.php`), but the API
login endpoint has no interactive form to let a caller actually set a new
password, unlike the legacy web login page — confirmed directly by Fabien
logging in through the real UI and getting the expected password-reset
prompt. `ADMIN_PASSWORD_EXPIRED` is therefore only useful for the web login
flow, not for API-driven testing.

## rrdcached sidecar (`rrdcached` service), MON-213837

A write-cache TCP sidecar for Central's merged RRD output, disabled by
default (`RRDCACHED_PORT` unset) and only turned on once a `BROKER_TAG`
carrying MON-213839's fix is in use (C12). `rrd_cached_option`/`rrd_cached`
in `cfg_centreonbroker_info` are toggled idempotently by `12-install.sh`.

**DESIGN CHANGE (2026-11-02)**: originally shipped with
`network_mode: "service:centreon-broker"` — `broker/rrd/src/output.cc`
hard-coded `"localhost"` for its rrdcached connection, so sharing
`centreon-broker`'s network namespace was the only way to reach it at all.
That broke in practice: `centreon-broker`'s own image refuses to start
(`exit 1`, package-default config guard) until gorgone delivers a real
config, and `restart: unless-stopped` churns its network namespace on every
restart — `rrdcached` (`network_mode: service:centreon-broker`) failed to
even start with `OCI runtime create failed: namespace path: lstat
/proc/<pid>/ns/net: no such file or directory` while that churn was
happening. Fixed at the source instead of papering over it in compose:
MON-213839 (G9) adds a `host` key to the rrd output block
(`find_param(cfg, "host", false, "localhost")` in `broker.class.php`), so
`cbd` can now dial out to a separate container. `rrdcached` is therefore its
own independent service/hostname here, sharing only the RRD volumes, not a
network namespace — `12-install.sh` upserts `host =
${RRDCACHED_HOST:-rrdcached}` in the same `cfg_centreonbroker_info` row
group whenever `RRDCACHED_PORT` is set (and drops that row when it isn't,
regardless of `RRDCACHED_HOST`), and `CENTREON_RRDCACHED_HOST` on `fpm`
(centreonGraph{,Ng,Status}.class.php's own remote-rrdcached setting) is kept
aligned to the same default — the two are separate env vars read by
different code paths, not derived from one another, so a future change to
one has to be mirrored in the other by hand.

An explicit `RRDCACHED_HOST` always wins over the `rrdcached` default — the
Helm chart relies on this, passing `127.0.0.1` for its own sidecar-in-pod
topology (its `rrdcached` listens on `0.0.0.0`, and `localhost` can resolve
to `::1` there).

## Autodiscovery save-worker (`autodisco-save-worker` service), MON-213829

Drains `mod_auto_disco_save_request`, writing discovered hosts/macros
straight to the database. No dedicated binary: it's `centreon-autodiscovery`'s
own `save-worker` console command, run as `www-data` (`bin/console` refuses
root) on the same `fpm` image/entrypoint pipeline as the `fpm` service
itself, with `php-fpm` swapped for the console command via `command:`. A
config-only replica of `fpm` (`RUN_INSTALL=true`, `INSTALL_DB_STEPS=false`,
`RUN_CONFIG_DELIVERY=false`) — the exact pattern `12-install.sh` already
documents for N `fpm` replicas sharing one install (there, a Helm install
Job; here, `fpm` itself). Contract confirmed directly with the
centreon-modules/autodisco owners (M3, `AUTODISCO_TAG=MON-213817-autodisco-save-worker`):

- **Direct DB access, not API-only**: reads the save-request queue and jobs,
  writes hosts/macros with the same `MYSQL_*` env as `fpm`. The internal API
  (`CENTREON_INTERNAL_API_BASE_URL`) is only used for exporting/reloading
  scheduled auto-add jobs — needs C8's internal-URL fix for that.
- **Liveness, not `/tmp/docker.ready`**: the worker touches
  `/tmp/autodiscovery-worker.alive` at the end of every successful poll loop;
  the healthcheck above checks that file's mtime, not install completion.
- **License and Vault, read-only, shared with `fpm`**: `/etc/centreon/license.d`
  (new `centreon-license` volume, also mounted on `fpm`, which is where the UI
  actually writes an uploaded license) and `/var/lib/centreon` (vault.json,
  via the existing `centreon-varlib` volume) — without Vault reachable, the
  save-worker's own fail-closed guard suspends saves rather than writing
  anything in clear.
- **Image requirement**: needs the SourceGuardian autodiscovery module +
  `php8.4-sourceguardian-loader` baked in (C5's `modules_fetcher`/
  `autodisco_source` stages) — `bin/console list centreon-autodiscovery` has
  to show `save-worker` for this to be anything but a fast, silent no-op.

**Only ever tested with a single replica** — the owners haven't validated
concurrent `save-worker` instances draining the same queue, so this service
is deliberately not scaled in this compose file.

**Local-testing gotcha, not a product bug**: a plain `docker compose build`
in this directory never reads `dependencies.env`'s `AUTODISCO_TAG` —
`docker-central.yml` passes it as a `--build-arg AUTODISCO_IMAGE=...`, so a
manual rebuild needs the exact same flag by hand, or the Dockerfile's own
`:develop` default silently wins (develop's autodiscovery module predates
`save-worker`, so `bin/console centreon-autodiscovery:save-worker` fails
with "There are no commands defined in the centreon-autodiscovery
namespace."). A second trap once the right `AUTODISCO_IMAGE` is built:
Compose names a built image `<project>-<service>`, so mixing `-p` flags
between a `build` and an `up` (or across `docker compose` invocations
against different project names) silently reuses a stale image under the
other project's name instead of the one just built — confirmed this is what
happened on a first pass here. Rebuilt and brought up under one consistent
project name: `autodisco-save-worker` reaches `healthy`, its own log shows
`Autodiscovery save worker <host>:<pid> started (poll every 5s)`.

## Explicitly out of scope for this first pass

- Not wired into `.github/workflows/web.yml` yet (no CI job builds/publishes these).
- `www/locale` (translation build) isn't generated — UI falls back to English strings.
- The 4 T0 baked-in modules (Autodiscovery, License Manager, Monitoring Connector
  Manager, IT Edition extensions) aren't vendored — their source isn't in this repo.
- No productised init step (T9): `12-install.sh` is a dev/CI convenience
  (opt-in, hardcoded fixture credentials), not a real init-container boundary
  with secrets. `APP_SECRET` stays as the literal `%APP_SECRET%` placeholder
  from `.env` either way — never generated by this image.
- **`debug=true` step (`printDebug()`'s `centengine -v` shell-out) now works,
  not just shimmed.** `/usr/sbin/centengine` in the `fpm` image is a fake
  binary printing `Total Warnings: 0`/`Total Errors: 0` and exiting 0 (same
  trick the historical bookworm-tmp prototype used, see ticket 03 in the
  vault). The shim alone wasn't enough: `printDebug()`'s own query, `SELECT
  nagios_bin FROM nagios_server WHERE localhost = '1' ...`, matched nothing
  once Central's row is `0` (`sh: 1: : Permission denied`, an empty binary
  path). **Real code fix, not a Docker-only workaround**: changed that query
  to `WHERE is_default = '1'` instead
  (`www/include/configuration/configGenerate/common-Func.php`). `is_default`
  ("Is default poller?"/"Main poller" in the UI) is a real, already-existing,
  UI-editable column that identifies Central independently of `localhost`,
  kept in sync with it under normal deployments
  (`DB-Func.php::defineLocalPollerToDefault()`) — `localhost` is a
  delivery-routing flag (`moveFiles.php`'s local-copy-vs-gorgone branch), not
  an identity flag, and a split-container Central can correctly be
  `localhost=0` while still being the one `is_default=1` row. Verified end to
  end: `GET .../generate` with `debug=true` returns `statuscode: 0`, green
  `Total Warnings: 0`/`Total Errors: 0` in the response.
- **`MonitoringServerConfigurationRepositoryApi`'s internal callback fixed,
  same bug class ticket 03 already found for `InternalApiClient`, second
  half now fixed too.** `generateConfiguration()`/`moveExportFiles()`/
  `reloadConfiguration()` (used by "Generate Configuration Files" always with
  `debug=true`, and by the `GET .../monitoring-servers/generate-and-reload`
  button) call back into `generateFiles.php`/`moveFiles.php`/
  `restartPollers.php` over real HTTP. `initUri()` built that URL from
  `$_SERVER['SERVER_NAME']` alone, no port, which resolves to php-fpm calling
  back into **itself** in a split topology (`"Failed to connect to localhost
  port 80"` — nothing listens on 80 in the `fpm` container). Fixed by routing
  through `InternalApiClient::convertToLocalUrl()`, the exact mechanism
  already used for the add-host case, same `CENTREON_INTERNAL_API_BASE_URL`
  env var already set in this compose file — no new config needed, extends
  an already-necessary one. Falls back to `$_SERVER['SERVER_ADDR']` (the
  server's own internal address, never whatever host/IP an external client
  used to connect) when that env var is unset, so a monolithic deployment
  needs no change at all. Verified end to end with the real button's own
  call shape (`GET /centreon/api/latest/configuration/monitoring-servers/generate-and-reload`,
  no server id, all pollers): `HTTP 204`.
- "Generate Configuration Files" / "Move Export Files" for Central now works
  end to end through Pilier 2 (gorgone), see "Central routed through gorgone
  instead of local `copy()`" above — `12-install.sh` flips Central's
  `nagios_server.localhost` to `0` so `moveFiles.php` takes the
  `legacycmd`/`REMOTECOPY` branch instead of the local `copy()` one. Still
  not zero-shared-volume though: `REMOTECOPY`/`legacycmd` reads the source
  config files off gorgone's **own local disk**, path only, never file
  content over the API, so `fpm` and `gorgone-central` still need to share
  the generated-config cache volume regardless.
- Root vs non-root not decided (currently whatever `httpd:2.4`/`php:8.4-fpm`
  default to).
