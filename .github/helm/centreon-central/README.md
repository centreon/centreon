# centreon-central Helm chart

Kubernetes deployment of a Centreon central node built on the split httpd /
php-fpm images, gorgone central + pullwss, centengine and centreon-broker.
Port of `../../docker/centreon-web-split/docker-compose.yml`, reorganised so
the web tier scales horizontally.

- `values.yaml`: single-node dev/test defaults (1 replica each, RWO volumes,
  bundled MariaDB).
- `values-prod.yaml`: production overlay (fpm/httpd autoscaling + PDB, RWX
  storage, external DB, ingress, resources). Fill the `CHANGE_ME` values.

Status: validated on a single-node k3d cluster with both profiles (prod
overlay with RWO storage). Not yet validated on a multi-node cluster, RWX
storage, a managed database or remote pollers.

## Topology

| Workload | Kind | Replicas | Role |
|----------|------|----------|------|
| `install-<id>` | Job | once | DB schema install/upgrade, modules, SQL patches. Writes `options.helm_install_id` when done |
| `config-delivery-<id>` | Job | once | Resyncs gorgone nodes, waits for the Central poller, generates + delivers its config, exits |
| `fpm` | Deployment | N (HPA) | Web backend. Per-container init only, waits for the install marker |
| `httpd` | Deployment | N (HPA) | Static assets, FastCGI to fpm, wss to gorgone-central |
| `gorgone-central` | Deployment | 1 | Orchestrator (TYPE=central) |
| `broker` | StatefulSet | 1 | central-broker-master (RRD merged in), Service 5669 for pollers, rrdcached sidecar |
| `central-poller` | StatefulSet | 1 | gorgone (poller) + centengine for Central itself |
| `poller-<name>` | StatefulSet | 1 per poller | Remote pollers from `pollers:` (untested) |
| `db` | StatefulSet | 1 | Bundled MariaDB (dev) |
| `cron-<name>` | CronJob | per schedule | Centreon's scheduled jobs (`cronJobs`, on by default) |
| `autodiscovery-worker` | Deployment | 1 | Saves host discovery results (`autodiscoveryWorker.enabled`) |
| `valkey` | StatefulSet | 1 | PHP sessions (`valkey.enabled`) |

Scaling Centreon means adding pollers, not replicas: gorgone-central, the
broker and each poller are singletons by design.

`<id>` is a hash of the fpm image reference, the chart version,
`install.runId` and the install's environment (the Job names also end with
a hash of their spec): a new image tag, chart version or install-related
value runs both Jobs again (upgrade). Use immutable tags; rebuilding the same
tag does not re-run them. Both are plain Jobs, not Helm hooks (a hook would
deadlock with `--wait` / ArgoCD PostSync, since fpm waits for the install).

### Upgrading from 0.12 or earlier (Deployments to StatefulSets)

0.13 turns the Central poller and the broker into one-replica StatefulSets
(never two pods on their RWO volumes, e.g. after a node loss). They keep the
same PVCs. Helm creates the StatefulSets before deleting the Deployments, so
stop the old pods first, otherwise two engines briefly share the volumes:

```powershell
kubectl -n <ns> scale deploy <release>-central-poller <release>-broker --replicas=0
helm upgrade <release> ...
```

The Central poller's host name changes from `<release>-central-engine-otel`
(`centralPoller.hostname`, removed) to `<release>-central-poller-0`. The
persisted default CA keeps its CN and fingerprint; agents that check the
name should move to `centralPoller.otel.tls`.

Remote pollers (`pollers[]`) got `podManagementPolicy: Parallel` in 0.11
(immutable): `kubectl delete sts <release>-poller-<name> --cascade=orphan`
before upgrading from 0.10 or earlier.

## Why some containers share a pod

- Poller pods: gorgone + centengine, because centengine's external command
  file is a FIFO that gorgone writes to, which only works within one kernel.
  With traps, snmptrapd + centreontrapd join them (centreontrapd writes the
  same FIFO); with VMware, centreon-vmware (the engine's plugins reach it as
  `centreon-vmware:5700`, a host alias to the pod itself).
- Broker pod: cbd + the rrdcached sidecar (cbd sends its RRD updates to
  `127.0.0.1:42217`).

## Volumes

Few volumes, a directory per use inside each (subPath). On a cloud the
number of volumes costs more than their size: an EBS gp3 volume is billed 1
GiB minimum and counts in the node's attachment limit (EFS bills usage only).

| Volume | Directories | Writers | Readers |
|--------|-------------|---------|---------|
| centreon-web (shared) | `media` (www/img/media), `sessions` (PHP, not with Valkey), `license` (/etc/centreon/license.d), `cache-config` (only when fpm can scale) | fpm | httpd (media), cron send-stats and autodiscovery worker (license) |
| centreon-rrd (shared) | `status`, `metrics` (RRD, ~330 KiB per file) | broker (cbd, rrdcached sidecar) | fpm |
| poller-broker (shared) | /etc/centreon-broker of the Central poller | central-poller gorgone | broker, centengine |
| poller-data (Central poller; one per remote poller) | `engine`, `etc`, `pki` (CMA CA), `gorgone-data` (gorgone's history DB), `traps-config`, `broker` (remote) | gorgone, centengine | centreontrapd, vmware |
| poller-spool (Central poller; one per remote poller) | `centlog` (engine logs, retention.dat), `cbmod-cache` (queue while the broker is unreachable) | centengine | |
| broker-cache | broker queue during a database outage | broker | |

A poller's volumes are split by growth: poller-spool holds what grows when
the broker is unreachable or the logs are verbose, so filling it stops the
logs and the queue but not the config, the CMA CA or the traps DB.

No volume for `/var/lib/centreon` (commands and config files reach gorgone
through its API, Vault's file is generated per pod), for the traps DB on
the Central side (generated in the image's `/etc/snmp/centreon_traps` and
sent by the same request) nor, with one fpm replica, for the generated
config (an emptyDir: the UI generates and moves it in two requests, which
only need to share it when they can reach two replicas). The Symfony cache
(`/var/cache/centreon/symfony`) stays local to each fpm pod.

### Upgrading from 0.40 or earlier: volumes

Up to 0.40 every directory had its own volume (16 for a Central, 6 per
remote poller). Upgrade once with `persistence.migrateLegacyVolumes=true`:

- the old PVCs stay in the release, annotated `helm.sh/resource-policy:
  keep`; init containers (fpm, broker, Central poller, each remote poller)
  copy them into the new volumes, check every file (name and size) and stop
  the pod on any mismatch or when the new volume is too small. A marker
  under the volume root makes each copy run once. The CMA CA keeps its
  files, so the agents' pinned fingerprint does not change;
- fpm switches to `Recreate` meanwhile (the old volumes are ReadWriteOnce);
- remote pollers: their volumeClaimTemplates change, so delete each
  StatefulSet with `--cascade=orphan` before the upgrade (its old PVCs are
  kept and mounted read-only by the migration).

Then upgrade again with the flag back to `false`: the old PVCs are orphaned,
not deleted. Delete them by hand once checked (`kubectl get pvc -l` on the
release, the ones annotated `centreon.com/legacy-volume`, and for remote
pollers `poller-<x>-<release>-poller-<name>-0`). Not migrated, on purpose:
`centreon-varlib` (nothing left in it) and `centreon-cache-config`
(regenerated). The licence volume only exists since 0.34: from an older
release it is skipped when the chart can read the cluster (helm CLI);
rendered without access (ArgoCD), it is expected, so create an empty one
first.

The flag has no default, on purpose. With the helm CLI the chart reads the
cluster: it refuses to upgrade a release that has the old volumes but not
the new ones, even with an explicit `false` (that would drop the old
volumes from the release without copying them). Rendered without cluster
access (ArgoCD, `helm template`) it cannot tell "no old volume" from "no
access", so it refuses to render until the flag is set: `true` for the
upgrade from 0.40 or earlier, `false` for a new install or once migrated.
An ArgoCD sync with pruning would otherwise delete the old volumes, and with
a `Delete` reclaim policy their data, before any copy. The PVCs rendered
during the migration, and every PVC with `keepOnUninstall`, also carry
ArgoCD's `argocd.argoproj.io/sync-options: Delete=false,Prune=false`
(ArgoCD ignores helm's resource-policy).

### Upgrading from 0.41: poller-spool

0.42 moves `centlog` and `cbmod-cache` out of poller-data into the new
poller-spool volume, without copying them (0.41 was never released): the
engine logs, retention.dat and a pending cbmod queue start empty, the old
directories stay unused in poller-data. Keep the existing poller-data size
(`persistence.local.pollerData.size=2Gi`, the 0.41 default): a PVC cannot
shrink. Remote pollers get a second volumeClaimTemplate: delete each
StatefulSet with `--cascade=orphan` before the upgrade.

## Event queues across restarts

Broker processes keep their queue in memory and save it to their
`cache_directory` on a clean stop. The default directories
(`/var/lib/centreon-engine`, `/var/lib/centreon-broker`) hold image files and
cannot take a volume, so the install Job points Central's broker configs at
dedicated volumes:

| Process | Queues while | Value | Volume |
|---------|--------------|-------|--------|
| Central cbmod (Central poller pod) | the broker is down | `centralPoller.cbmodCacheDirectory` | poller-spool (`cbmod-cache`) |
| central-broker-master (broker pod) | the database is down | `broker.cacheDirectory` | `brokerCache` |

A reload does not switch `cache_directory`: a change of these directories
takes effect at the next restart of the pod concerned. Abrupt deaths
(node loss, OOM kill) still lose the in-memory part. Remote pollers need the
same "Cache directory" set in their broker configuration in Centreon.

## Centreon Monitoring Agents (CMA)

Agents dial the Central engine on 4317 (Service `<release>-central-engine-otel`,
LoadBalancer for agents outside the cluster). With `encryption: full` they
check the certificate against the FQDN of their `endpoint`.

The engine's default CA (`/etc/pki/centreon-engine/default_cma_ca.*`,
persisted in poller-data, `pki`) has CN = host name and no SAN. The pod host name
is `<release>-central-poller-0` (forced by the StatefulSet controller), so it
never matches an external FQDN. Provide the TLS identity instead (same value
names as the `centreon-poller` chart):

1. A certificate with the FQDN agents dial as SAN, either:
   - **cert-manager**: `centralPoller.otel.tls.certManager.enabled=true`,
     `dnsNames`, `issuerRef` (your Issuer / ClusterIssuer, e.g. a CA Issuer
     whose CA the agents trust). The chart creates the `Certificate`, the
     Secret is `<release>-central-otel-tls`.
   - or a `kubernetes.io/tls` Secret of yours (`tls.crt`, `tls.key`, optional
     `ca.crt`): `centralPoller.otel.tls.existingSecret`.

   The CA's subject must differ from the server certificate's.
2. The init container copies it to `/etc/pki/centreon-central`
   (`centralPoller.otel.tls.mountPath`, must stay under `/etc/pki/`), owned by
   centengine, key `0600` (not readable by gorgone). A renewed certificate is
   picked up at the next pod restart.
3. In the Central's agent configuration in Centreon: public certificate
   `<mountPath>/tls.crt`, private key `<mountPath>/tls.key` (CA certificate
   `<mountPath>/ca.crt` if any), then export with **Restart**.
4. Give the agents the CA (`ca_certificate`, `install_cma.ps1 -CA <file>`).

## SNMP traps and VMware connector

Per poller: `centralPoller.traps` / `centralPoller.vmware`, or
`pollers[].traps` / `pollers[].vmware` (same keys, the remote poller's
`traps.service` is merged over `centralPoller.traps.service`).

**Traps** add `snmptrapd` (162/udp, spools) and `centreontrapd` (matches the
spooled traps against the delivered definitions, writes results into the
engine's command FIFO) to the poller pod, the `traps-config` directory of
poller-data (`/etc/snmp/centreon_traps`, read-only in `centreontrapd`) and a
Service `<release>-central-snmptrap` / `<release>-poller-<name>-snmptrap`
(UDP 162).

- Central side: fpm writes the traps DB (`generateSqlLite`,
  `<poller id>/centreontrapd.sdb`, in the container, no volume) and sends its content to
  gorgone-central in the same request (`SYNCTRAP`), which delivers it to the
  poller (Configuration > SNMP Traps > Generate).
- First database: the config-delivery Job generates and sends it to every
  poller with traps (the Central poller, and remote pollers found by uid),
  after the Central's export. Without it `centreontrapd` exits until someone
  generates the traps (it can't create the missing file on its read-only
  directory, and an empty file fails too). Adding traps to a remote poller
  changes the Job, so it runs again for that poller. A poller not connected
  within 5 minutes is reported in the Job's log, not retried: generate from
  the UI then. Trap changes afterwards are generated from the UI as usual.
- `centreontrapd` finds the host by **address**: the trap's source IP, or its
  reverse DNS name. Keep the source IP: `externalTrafficPolicy: Local` on a
  cloud / MetalLB LoadBalancer. k3s' klipper LoadBalancer SNATs it anyway, as
  does any Service with `externalTrafficPolicy: Cluster`.
- `snmptrapd` runs as uid 900: the pod gets the
  `net.ipv4.ip_unprivileged_port_start=162` sysctl (allowed by `baseline`).

**VMware** adds `centreon-vmware` (5700), reached by the engine's VMware
plugins as `centreon-vmware:5700` (host alias to the pod itself); its config
`/etc/centreon/centreon_vmware.json` is delivered by the central, and the
connector restarts on each update. No public image (licensed VMware Perl
SDK): build it from centreon-plugins with the SDK, push it to a private
registry, set `images.vmware` and `imagePullSecrets`.

## Security

**NetworkPolicies** (`networkPolicy.enabled`, default on; needs a CNI that
enforces them, k3s does):

| Policy | Ingress allowed |
|--------|-----------------|
| `<release>-gorgone-central` | 8085 from fpm, cron + config-delivery Job, 8087 from httpd |
| `<release>-fpm` | 9000 from httpd |
| `<release>-broker` | 5669 from anywhere (pollers), 51001 (cbd gRPC) from the Central poller pod only, 42217 from fpm (rrdcached) |
| `<release>-db` (bundled DB only) | 3306 from fpm, install, config-delivery, cron, gorgone-central, broker, autodiscovery-worker |
| `<release>-valkey` (`valkey.enabled`) | 6379 from fpm |
| `<release>-pollers` | 4317 (`otelFrom`), 162/udp when traps (`trapsFrom`) |

httpd stays open (Ingress / users). Kubelet probes are not affected.

**Service `cbd`** (port 51001, cbd's gRPC API): after an export, gorgone's
action module reloads the broker with a gRPC `SignalProcess` call to
`cbd:51001` (its default host; cbd raises SIGHUP on itself). No auth, and it
can also stop cbd: ClusterIP only, never on `centreon-broker`. cbd must listen
on all interfaces for it (`grpc.listen_address` in the delivered broker JSON),
not only on localhost. The broker image has no HTTP API any more (no 8080,
no `centreon-broker-api` Service since 0.47).

**Pod Security Standards**: every pod passes `baseline` (checked with a
server-side dry-run of `pod-security.kubernetes.io/enforce=baseline`), not
`restricted` (root seed init containers, no seccomp profile /
`allowPrivilegeEscalation: false` / `runAsNonRoot` on most containers).
Recommended namespace labels: `enforce=baseline`, `warn=restricted`,
`audit=restricted`.

## rrdcached (`rrdcached.enabled`, on by default)

An rrdcached sidecar in the broker pod (image `--target rrdcached`, port
42217) receives the broker's RRD updates and writes them in batches, instead
of the broker writing every update itself. On by default for the storage:
the RRD files live on a PVC, often network RWX storage (EFS, NFS), where
every small write is expensive. `rrdcached.enabled: false` goes back to
direct writes. When enabled:

- The install Job sets `rrd_cached_option=tcp`, the port (`RRDCACHED_PORT`)
  and the host (`RRDCACHED_HOST=127.0.0.1`: the sidecar, in cbd's own pod)
  on the central rrd output, then the config delivery runs again. Brokers
  without the `host` key of the rrd output connect to `localhost`, hardcoded:
  same pod either way.
- fpm flushes rrdcached before drawing a graph (`CENTREON_RRDCACHED_HOST`,
  ClusterIP Service `centreon-rrdcached`, reachable from fpm only).
- Native sidecar: started (answers PING) before cbd, stopped after it, and
  flushes its pending updates on SIGTERM. Its journal is an emptyDir: updates
  not written yet are lost only on an abrupt pod death.

**Requirement**: a broker image whose TCP RRD output initializes its logger
(centreon-collect MON-213839). Without it the output's constructor leaves
`_logger` null and cbd segfaults on the first RRD update: with an older
broker image, set `rrdcached.enabled: false`.

### Upgrading from 26.12 or earlier: rrdcached

rrdcached was off by default. An upgrade
that does not set `rrdcached.enabled` turns it on: the install Job and the
config delivery run again, and the broker pod restarts with the sidecar.

## Exposure (Services and Ingress)

| Service | Port | Exposed? | Used by |
|---------|------|----------|---------|
| `httpd` | 80 | via the Ingress (or `httpd.service.type`) | users, REST API, pollers' pullwss |
| `centreon-broker` | 5669 BBDO | `broker.service.type` (LoadBalancer in prod, raw TCP: no Ingress) | Central poller, remote pollers |
| `cbd` | 51001 gRPC | never (ClusterIP, no auth, NetworkPolicy: Central poller pod only) | gorgone's reload after an export |
| `gorgone-central` | 8085, 8087 | never (NetworkPolicy) | fpm, cron, config-delivery Job, httpd |
| `fpm` | 9000 | never | httpd |
| `centreon-rrdcached` | 42217 | never (ClusterIP, NetworkPolicy: fpm only) | fpm (flush before a graph) |
| `valkey` | 6379 | never (ClusterIP, NetworkPolicy: fpm only; `valkey.enabled`) | fpm (PHP sessions) |
| `<release>-central-engine-otel` | 4317 | `centralPoller.otel.service.type` (LoadBalancer for agents outside the cluster) | CMA agents |
| `<release>-central-snmptrap`, `<release>-poller-<name>-snmptrap` | 162/udp | `centralPoller.traps.service.type` (with traps; LoadBalancer for devices outside the cluster) | SNMP devices |
| poller pods (engine gRPC) | 50155 | never (NetworkPolicy `<release>-pollers`: only 4317 reachable, from `networkPolicy.pollers.otelFrom`) | gorgone in the same pod |
| `db` | 3306 | never | install, config-delivery, fpm, cron, autodiscovery worker, gorgone-central, broker |

The Ingress routes `host/` to httpd: UI, API and the pullwss websocket of
pollers outside the cluster (`/centreon/gorgone/pullwss/websocket`). With
ingress-nginx, raise `proxy-read-timeout` / `proxy-send-timeout`
(`values-prod.yaml`), otherwise idle websockets are closed after 60s.

`httpd.serverName` is derived from the Ingress when left empty
(`https://<host>` with `ingress.tls`). Apache's `UseCanonicalName On` builds
redirects from it, so it must match exactly what users browse to (scheme,
host, port).

Verified on k3d with ingress-nginx and a self-signed certificate: `/` and
`/centreon` redirect to `https://centreon.local/...`, login 200 through the
Ingress, websocket upgrade 101 on the pullwss path, internal calls (install,
config delivery) unaffected by the https ServerName.

**DNS / hosts file.** In prod, a DNS record for `ingress.host` pointing at
the ingress controller's LoadBalancer; nothing in a hosts file. On k3d, to
open the UI in a browser: add `127.0.0.1 centreon.local` to
`C:\Windows\System32\drivers\etc\hosts` (admin) and port-forward the
controller on 443 exactly (the ServerName has no port, so redirects from
another port would lose it):

```powershell
kubectl --context k3d-centreon-helm -n ingress-nginx port-forward svc/ingress-nginx-controller 443:443
```

## Migrating an existing (VM) database

Point the chart at the existing database (`database.bundled=false`,
`database.externalHost`). The install Job sees the schema, runs the pending
updates, registers the baked-in modules, then applies the container-topology
adaptations, on every run and idempotently:

- `GRANT ALL` to the Centreon DB user from `%` (`createDbUser.php` only
  grants the install pod's IP: every fpm pod got `Access denied`);
- Central's `nagios_server`: `localhost=1` (it is the Central),
  `poller_type=docker` (gorgone-central registers it as a pullwss node,
  type 4, port 8087, instead of treating it as itself);
- `options.gorgone_api_address = gorgone-central`;
- Central's broker configs, found by role, never by `config_id`: master
  `db_host` = the new DB host, cbmod output host = `centreon-broker`,
  central-rrd-master merged into the master (one `rrd` output, no 5670
  forward), cbmod and master `cache_directory` on their volumes.

Only Central's rows are touched, never remote pollers'.

Verified on k3d with a real 25.10.18 database (monolith
`centreon-web-alma9:dev-25.10.x` install + test dataset, dumped and loaded
into a standalone MariaDB): upgrade to 26.09.1, data unchanged, login 200,
the 25.10 host and its 6 active services delivered to centengine and visible
in the realtime API, broker on the new DB. A second run (new `install.runId`)
changed nothing (still one rrd output).

Prerequisite, untested: carry the VM's `APP_SECRET` (from
`/usr/share/centreon/.env`) and salt (from
`/etc/centreon-engine/engine-context.json`) into the chart Secret, otherwise
anything already encrypted in the DB (`encrypt::` values, vault config) can't
be decrypted. The 25.10 test dataset had no encrypted value.

## Fixed Service names: one release per namespace

`db`, `fpm`, `httpd`, `gorgone-central`, `centreon-broker`, `cbd`,
`centreon-rrdcached` and `valkey` are not release-prefixed: the install
writes these hostnames into the Centreon DB or the images use them. In every poller pod `centengine` resolves to
`127.0.0.1`, so a poller's gorgone never reloads another poller's engine.

## gorgone-central API access

The gorgone image hardcodes its httpserver `allowed_hosts` to
`127.0.0.1/32` + `172.16.0.0/12` (Docker's default network). Pod IPs
(`10.42.0.0/16` on k3s) get a 401, which broke fpm's node-sync call and the
config-delivery Job. Pod IPs are ephemeral, so the chart disables the
allowlist (`GORGONE__GORGONE__MODULES__HTTPSERVER__ALLOWED_HOSTS__ENABLED`)
and replaces it with a NetworkPolicy: 8085 only from fpm, cron and the
config-delivery Job, 8087 only from httpd (verified on k3s: an unlabeled pod
is blocked). The API itself requires basic auth with the `centreon-gorgone`
service account (`centreonApiUsername`, Secret key `GORGONE_API_PASSWORD`):
the NetworkPolicy is defence in depth, keep it on unless the cluster is
dedicated to Centreon. Better fix: make the subnets configurable in the
gorgone image (`10-create-config.sh`, centreon-collect).

## Differences from the compose stack

- **Named-volume copy-up made explicit.** Init containers reuse each pod's
  own image as root and `cp -an` + `chown/chmod --reference` the same paths
  (no-op on restart; chown failures are non-fatal for RWX backends such as
  EFS access points). In poller pods `/etc/centreon` and
  `/var/lib/centreon-gorgone` are seeded from the gorgone image;
  `/etc/centreon-broker` is not seeded (the broker refuses its default).
- **Config delivered once, not every 60s.** compose re-runs "generate + move
  + reload" every minute; here the config-delivery Job does it once per
  install/upgrade and exits, then exports are the admin's job as on a VM (no
  silent export of staged edits, no reload every minute).
- **gorgone-central may start before the schema exists.** The
  config-delivery Job POSTs `/api/centreon/nodes/sync` until the Central
  poller is connected.
- **Probes.** fpm: startup + readiness on `/tmp/docker.ready`, liveness on
  9000. httpd: readiness on `/tmp/docker.ready`, liveness on 80. broker:
  readiness = cbd listening on 5669 (read from `/proc/net/tcp`, no
  connection); its rrdcached sidecar: startup + readiness on PING.
  gorgone-central: readiness on 8087. Autodiscovery worker: startup on
  `/tmp/docker.ready`, liveness on its heartbeat file. Valkey and the
  bundled DB: readiness on a ping. Poller pods (gorgone, centengine,
  traps, vmware): none.
- **Cold start, no restart loop.** The engine image waits for its cbmod
  config (`Waiting for /etc/centreon-broker/central-module.json`), the
  trapd image for centreontrapd's trap database, the broker image for its
  delivered config (`Waiting for /etc/centreon-broker/central-broker.json`),
  fpm, the autodiscovery
  worker and the config-delivery Job for the install Job's marker, and the
  CronJobs for the same marker read as the `centreon` database user. A
  fresh install converges in a few minutes.

## Running CLAPI

Run CLAPI (and the other `bin/` tools) in fpm as `www-data`, never as root,
which is what `kubectl exec` gives by default:

```sh
kubectl -n <namespace> exec -it deploy/<release>-fpm -c fpm -- \
  runuser -u www-data -- php /usr/share/centreon/bin/centreon -u admin -p '<password>' -a POLLERLIST
```

A configuration generation run as root (`APPLYCFG`, `POLLERGENERATE`)
recreates `/var/cache/centreon/config/{engine,broker}/<poller id>` as
`root:root` 0770 on the shared volume, and every later generation from the
web or the REST API fails ("Not writeable directory", HTTP 500 "consistency
error in the exported files"). Repair, in fpm: `chown -R www-data:www-data
/var/cache/centreon/config/engine /var/cache/centreon/config/broker
/var/cache/centreon/config/vmware`.

## Secrets

Never generated by the chart (`lookup` is empty under `helm template` /
ArgoCD, so APP_SECRET/SALT would rotate on each sync). Use
`secrets.existingSecret` with keys `APP_SECRET`, `SALT`,
`MYSQL_ROOT_PASSWORD`, `MYSQL_PASSWORD`, `GORGONE_API_PASSWORD` (the
`centreon-gorgone` service account, set by the install Job), optionally
`ADMIN_PASSWORD`, plus `VALKEY_PASSWORD` with Valkey and `VAULT_ROLE_ID` /
`VAULT_SECRET_ID` with Vault; or set the `secrets.*` values.
`secrets.appSecret` and `secrets.salt` have no default (the install fails
without them): give each 64 random hex characters, and never change them
afterwards. The admin password is `secrets.adminPassword` when set at the
first install, otherwise the image's install fixture.

## PHP sessions in Valkey

`valkey.enabled` runs Valkey (official image pinned by tag and digest, one
replica, Service `valkey` on 6379, reachable from fpm only) and makes fpm
store its PHP sessions there (`PHP_SESSION_HANDLER=redis`, with locking):
fpm replicas share sessions without the shared `sessions` directory, which
is then neither mounted nor seeded. Or point `valkey.external.host`/`port`
at an existing Valkey/Redis (password from `valkey.external.existingSecret`,
key `existingSecretKey`).

- Password: `secrets.valkeyPassword` (Secret key `VALKEY_PASSWORD`),
  required, never generated (a new value on each ArgoCD sync would restart
  Valkey and log everyone out). fpm gets it as `PHP_SESSION_REDIS_PASSWORD`
  and URL-encodes it itself: any character works. Valkey reads it from a
  private config file, not from its arguments.
- Storage: emptyDir by default (a Valkey restart logs users out, nothing
  else is stored there); `valkey.persistence.enabled` keeps the sessions on
  a PVC (AOF).
- Switching from files to Valkey (or back) logs users out once.

## Vault

`vault.enabled` stores credentials in HashiCorp Vault. No volume is shared
for it: every pod that reads Vault (fpm, autodiscovery worker, CronJobs,
gorgone-central, broker, poller pods) runs `vault-config.sh` (fpm image) as
an init container and gets, in an in-memory emptyDir:

- `/var/lib/centreon/vault/vault.json`, the path the web, gorgone and the
  generated broker JSON (`vault_configuration`) use. `role_id`/`secret_id`
  are encrypted with `APP_SECRET` and a per-file salt, the only format cbd and
  cbmod accept;
- the web's feature flags with Vault on (`FILE_FEATURE_FLAGS`);
- the system CA bundle plus `vault.caCert`: the web, gorgone and cbd all
  verify Vault's certificate, whose name must match `vault.address`;
- for cbd and cbmod, the `APP_SECRET=` line they read from
  `/usr/share/centreon/.env`.

Values: `vault.address`, `vault.port`, `vault.rootPath`, `vault.caCert`, and
`secrets.vaultRoleId` / `secrets.vaultSecretId` (Secret keys `VAULT_ROLE_ID` /
`VAULT_SECRET_ID`). The Vault configuration page of the UI no longer
persists: change the values. After enabling it, move the existing credentials
once: `php /usr/share/centreon/bin/migrateCredentials.php` in fpm.

## Database TLS

A server can require TLS from every client: AWS RDS MariaDB 11.8 does by
default (`require_secure_transport=ON`), anything else is refused with
"Connections using insecure transport are prohibited". `database.tls.enabled`
makes every client use TLS and, with `verifyServerCert` (default), check the
server certificate against `database.tls.caCert` (PEM, e.g. RDS's
`global-bundle.pem`) and its name: `database.externalHost` must then be the
name in the certificate (the RDS endpoint), not an alias or an IP.

The CA goes into a ConfigMap mounted at `/etc/centreon-db-tls/ca.pem` in every
client pod (same path everywhere: the web writes it into the broker's JSON).

| Client | How |
|--------|-----|
| web PHP (fpm, install and config-delivery Jobs, autodiscovery worker) | `DATABASE_SSL_ENABLED` / `DATABASE_VERIFY_SERVER_CERT` / `DATABASE_CA_PATH` written into `.env.local.php` at start (php-fpm clears the workers' environment) |
| mysql CLI (install, waits) | `[client]` options instead of `skip-ssl` |
| cron PHP jobs | the same variables, in `$_ENV` (`cron/*.php` never load Dotenv) |
| cron Perl jobs (eventReportBuilder, dashboardBuilder) | `db_ssl` / `db_ssl_verify` / `db_ssl_ca` in `conf.pm`, turned by `centreon::common::db` into DSN attributes with `mysql_ssl_optional=1` (DBD::mysql on libmariadb, as for gorgone) |
| broker SQL outputs | `db_ssl_enabled` / `db_ssl_verify_cert` / `db_ssl_ca` in the JSON the web generates |
| gorgone-central | DSN attributes `mysql_ssl=1;mysql_ssl_optional=1;mysql_ssl_ca_file=…` (DBD::mysql on libmariadb refuses `mysql_ssl=1` alone) |

`database.bundledTls.secretName` (dev/test) makes the bundled MariaDB require
TLS with a `kubernetes.io/tls` Secret whose names include `db`, to test all
this without RDS. Validated on k3d: every client works against the
TLS-required server, and every client refuses it with a wrong CA.

Not covered: RDS privileges. The install connects as `root` and runs
`GRANT ALL ... WITH GRANT OPTION`, while the RDS master user is usually
`admin` and may not hold those privileges.

## Local test on k3d

From PowerShell, at the repository root:

```powershell
k3d cluster create centreon-helm --no-lb --k3s-arg "--disable=traefik@server:0" --kubeconfig-switch-context=false
# Build the web images first, then load them into the node (`k3d image import`
# can silently import nothing on Windows). The collect images (gorgone,
# engine, broker, traps: `develop`, pullPolicy Always) are pulled by the
# cluster from docker.centreon.com: give the nodes registry credentials
# (k3d `--registry-config`) or set `imagePullSecrets`.
foreach ($img in 'centreon-web-split-fpm:latest','centreon-web-split-httpd:latest',
  'centreon-web-split-cron:latest','centreon-web-split-rrdcached:latest','mariadb:11.8') {
  docker save $img | docker exec -i k3d-centreon-helm-server-0 ctr -n k8s.io images import -
}
# APP_SECRET and SALT: no default, keep them for the life of the release.
$hex = { [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32)).ToLower() }
helm install centreon .github/helm/centreon-central `
  --kube-context k3d-centreon-helm -n centreon --create-namespace `
  --set secrets.appSecret=$(& $hex) --set secrets.salt=$(& $hex) `
  --set httpd.serverName=localhost:28080
kubectl --context k3d-centreon-helm -n centreon port-forward svc/httpd 28080:80
```

Then open http://localhost:28080/centreon/ (admin / Centreon!2021). To test
the prod overlay locally, add `-f values-prod.yaml` plus a small override
file (local images, bundled DB, `persistence.sharedAccessMode=ReadWriteOnce`,
`storageClass=local-path`, no ingress).

If the kubeconfig entry points at `host.docker.internal` and times out:
`kubectl config set-cluster k3d-centreon-helm --server=https://127.0.0.1:<port>`.

## Known gaps

- Untested: RWX on a real multi-node cluster (only simulated on k3d),
  managed DB (the install runs `GRANT ALL ... WITH GRANT OPTION` as root,
  likely refused on RDS), remote pollers (`pollers:`), APP_SECRET/salt carried
  over from a VM, a migrated DB whose broker configs were renamed or have a
  different history than a fresh install.
- PHP sessions are files on the shared volume by default; `valkey.enabled`
  moves them to Valkey (see "PHP sessions in Valkey").
- During an upgrade, old fpm pods keep serving while the install Job migrates
  the schema.
- `12-install.sh` is not resumable: the install Job has `backoffLimit: 0`; if
  it fails, wipe the DB and reinstall.
- The collect images default to the floating `develop` tag (pullPolicy
  Always); a broken nightly can leave `develop` stale. `values-prod.yaml`
  has `CHANGE_ME` placeholders for the web images (fpm, httpd, cron,
  rrdcached): set them, and pin immutable collect tags there too.
- Eventual GitOps home is the Infrastructure repo (ArgoCD).
