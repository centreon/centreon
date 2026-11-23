# Gorgone-less light Central PoC stack (MON-215451)

Exploratory PoC, not meant to be merged. Epic MON-215448. Interfaces: `contracts.md` §1-§6 (gls study folder).

## Topology

| Project | Services | Networks |
| --- | --- | --- |
| `gls-central` | db, valkey, fpm, httpd (host port 18180), rrdcached, `centreon-broker` (central cbd), `central-engine` | `central`, plus `gls-wan` for `centreon-broker` |
| `gls-poller` | `poller-engine` (centengine and cbmod) | `gls-wan` only, no published port |

- **No gorgone anywhere.** The poller opens the only connection: BBDO over gRPC to `centreon-broker:5669`, with TLS. Commands and configuration come back down that same stream (`remote_actions`).
- **TLS:** the poller holds only the CA (`/etc/centreon-broker/certs/ca.crt`). It checks that the central's certificate is named `centreon-broker`. There is no insecure mode.
- **Web to cbd:** fpm talks to the central cbd remote API (`http://centreon-broker:8099`, bearer token) on the internal network only.

## How the stack starts (`gls-e2e.sh up`)

1. **PKI:** a throwaway PKI is generated at run time in `.certs/` (gitignored, valid 1 day): a CA, the central cbd certificate, and a rogue CA plus certificate with the same name for the negative test. Nothing is committed.
2. **Central poller:** its first configuration is the web's local copy into the shared config volumes. cbd is not running yet, so the channel cannot deliver it. Then:
   - the central `poller_type` is flipped to `vm`;
   - the harness adds `remote_actions` to the central cbmod config, for this bootstrap only;
   - the central switches to `remote_channel=broker`.
3. **New poller, from env only:** it is created through the API, and the answer gives `id` and `uid`. The container starts with `CENTRAL_BROKER_HOST`, `POLLER_NAME`, `POLLER_ID`, `POLLER_UID` and the CA. The engine image renders a minimal bootstrap config, connects over TLS and announces `remote_actions`.
4. **First configuration through the channel:** `generate-and-reload` on the new poller. The poller restarts on the delivered configuration and reports a `config_version`.

## Prerequisites

- **Docker Engine with compose v2, and bash, curl, jq, openssl.**
  - Windows: run from **WSL** (Ubuntu), with Docker Desktop's WSL integration enabled.
  - Do not use Git Bash: its path conversion breaks the openssl and `docker exec` calls.
- **Registry access:** `docker login docker.centreon.com` with your Harbor credentials. The registry is internal, so you may need the VPN. Tags are pinned in `images.env`.
- **RAM:** about 4 GB free for the 9 containers. This is an estimate, not measured. MariaDB and php-fpm take most of it.
- **Free host port 18180.** Nothing else is published.

## Run

From the repository root, in WSL:

```sh
docker login docker.centreon.com
.github/docker/gls/scripts/gls-e2e.sh up        # PKI, central, new poller, first export (~10 min)
.github/docker/gls/scripts/gls-e2e.sh step0     # no gorgone, data upstream, poller outbound-only, TLS cbmod
.github/docker/gls/scripts/gls-e2e.sh c1 poller # realtime: forced check, downtime, ack (also: c1 central)
.github/docker/gls/scripts/gls-e2e.sh c2 poller # export + reload applied (also: c2 central)
.github/docker/gls/scripts/gls-e2e.sh plugins   # plugins.json deployed and the package installed
.github/docker/gls/scripts/gls-e2e.sh c3 stop   # disconnected poller: explicit error <= 30 s (also: partition, pause)
.github/docker/gls/scripts/gls-e2e.sh tls-negative
.github/docker/gls/scripts/gls-e2e.sh down      # removes only the gls-* projects, their volumes and gls-wan
```

- Results are printed as `[ok]`/`[ko]` lines. Logs and API answers go to `$TMPDIR/gls-e2e/` (`/tmp/gls-e2e` on Linux/WSL, `C:/Users/<you>/AppData/Local/Temp/gls-e2e` from Git Bash), override with `LOG_DIR`.
- The new poller's `id` and `uid` are saved in `poller.env` in that directory.
- `up` expects a fresh stack: run `down` before running it again.

## UI and API

- UI: <http://localhost:18180/centreon/> (keep the trailing slash). Log in as `admin` / `Centreon!2021`, the install default, unless `ADMIN_PW` was changed.
- Poller list: *Configuration > Pollers*. Export from the UI goes through the same channel as the API.
- REST: `http://localhost:18180/centreon/api/latest/` (login: `POST /login`).

## Watch the logs

The manual compose commands need the image pins:

```sh
cd .github/docker/gls
set -a; . ./images.env; set +a
docker compose -p gls-poller  -f docker-compose.poller.yml  logs -f poller-engine
docker compose -p gls-central -f docker-compose.central.yml logs -f central-engine centreon-broker
docker compose -p gls-central -f docker-compose.central.yml logs -f fpm
```

What to look for:
- **Poller:** realtime commands appear as `EXTERNAL COMMAND: ...`; reloads as `Reload configuration finished.`.
- **Central cbd:**
  - one `remote api: commands request <id> poller <uid>: <STATUS>` line per command;
  - `remote api: deployment ... : <STATUS>` for exports;
  - `Peer poller ... connected/disconnected` for the stream.

Connected pollers as cbd sees them, through fpm:

```sh
docker compose -p gls-central -f docker-compose.central.yml exec -T fpm php -d allow_url_fopen=On -r '
  $c = stream_context_create(["http" => ["header" => "Authorization: Bearer " . getenv("CENTREON_BROKER_API_TOKEN")]]);
  echo file_get_contents(getenv("CENTREON_BROKER_API_URL") . "/v1/pollers", false, $c), "\n";'
```

## CI

`.github/workflows/gls-poc-e2e.yml` runs everything above on every push to the PR that touches these files. It pulls the images pinned in `images.env`, with `GATE_CRITERIA=true`, and uploads `/tmp/gls-e2e` as the `gls-e2e-logs` artifact.
