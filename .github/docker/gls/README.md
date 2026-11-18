# Gorgone-less light Central PoC stack (MON-215451)

Exploratory, not meant to be merged. Epic MON-215448.

## Topology

| Project | Services | Networks |
| --- | --- | --- |
| `gls-central` | db, valkey, fpm, httpd (host 18180), rrdcached, `centreon-broker` (cbd), `central-engine` | `central`, plus `gls-wan` for `centreon-broker` |
| `gls-poller` | `poller-engine` (centengine and cbmod) | `gls-wan` only, no published port |

No gorgone. The poller opens the only connection, BBDO to `centreon-broker:5669`. Nothing is published on host port 5669.

## Step 0 (current images, no downstream channel yet)

- **Central poller:** fpm mounts the central engine and broker config volumes. The web delivers through its local copy (`moveFiles.php`, `localhost=1`): `gls-e2e.sh up` flips the central `poller_type` from `docker` to `vm`. This is bootstrap-only, removed once the BBDO channel delivers the central poller.
- **Remote poller:** created with `poller_type=vm`. `gls-e2e.sh up` copies the web-generated files (`POLLERGENERATE`) into the poller volumes out of band (`GLS_SEED_POLLER=1`).
- The engine image's `05-engine-config.sh` forces engine gRPC on `0.0.0.0`. It is replaced by a no-op (`overrides/`), so engine gRPC stays on `127.0.0.1`.

## Run

CI: `.github/workflows/gls-poc-e2e.yml`. Change `images.env` on the PR to test new images.

Local, with light pulls only:

```sh
.github/docker/gls/scripts/gls-e2e.sh up
.github/docker/gls/scripts/gls-e2e.sh step0
.github/docker/gls/scripts/gls-e2e.sh c1 poller   # c1|c2 central|poller, c3 stop|partition|pause, plugins
.github/docker/gls/scripts/gls-e2e.sh down        # removes only the gls-* projects and gls-wan
```
