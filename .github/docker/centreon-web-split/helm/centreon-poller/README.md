# centreon-poller

Centreon remote poller on Kubernetes: one release = one poller, attached to a
central running anywhere (Helm, Docker or packages).

The pod runs `gorgone` (pullwss client of the central) and `centengine`
(with cbmod, the broker module). They must share a pod: the engine's external
command file is a FIFO gorgone writes to, and gorgone restarts/reloads its
engine over gRPC on `centengine:50155` (host alias to 127.0.0.1).

The pod spec is ported from the `centreon-central` chart's `_poller.tpl`.

## Install

1. In Centreon, **Add Poller**: note its `--uid` and its pullwss token
   (`<token-name>:<token-value>`).
2. In the poller's broker configuration in Centreon:
   - output host = the central broker endpoint reachable from this cluster
     (raw TCP 5669, LoadBalancer or NodePort — not an Ingress);
   - **Cache directory** = `/var/cache/centreon-engine` (`engine.cbmodCacheDirectory`).
3. Create the Secret (the central's `APP_SECRET` and `SALT` must match):

   ```powershell
   kubectl -n pollers create secret generic poller-1 `
     --from-literal=GORGONE_TOKEN='<token-name>:<token-value>' `
     --from-literal=APP_SECRET='<central APP_SECRET>' `
     --from-literal=SALT='<central SALT>'
   ```

4. Install:

   ```powershell
   helm install poller-1 . -n pollers `
     --set poller.name=Poller-1 `
     --set poller.id=<uid> `
     --set central.host=centreon.example.com `
     --set secrets.existingSecret=poller-1
   ```

5. Export the poller configuration from the central, then restart the engine
   once (a reload does not switch cbmod to its cache directory).

`centengine` crash-loops until the first configuration lands: expected.

## Centreon Monitoring Agents (CMA)

Agents dial the poller on 4317 (`engine.otel.service`, LoadBalancer for agents
outside the cluster). With `encryption: full` they check the poller
certificate against the FQDN of their `endpoint`.

The engine's default CA (`/etc/pki/centreon-engine/default_cma_ca.*`) has
CN = host name and no SAN. The pod host name is `<release>-0` (forced by the
StatefulSet controller), so it never matches an external FQDN. Provide the
TLS identity instead:

1. Create a `kubernetes.io/tls` Secret (`tls.crt`, `tls.key`, optional
   `ca.crt`) with the FQDN agents dial as SAN, e.g. with cert-manager. A
   self-signed CA also works: the engine issues its server certificates from
   it and copies its SANs.
2. `--set engine.otel.tls.existingSecret=<secret>`. The init container copies
   it to `/etc/pki/centreon-poller`, owned by centengine (key `0600`, not
   readable by gorgone). A renewed certificate is picked up at the next pod
   restart.
3. In the poller's agent configuration in Centreon: public certificate
   `/etc/pki/centreon-poller/tls.crt`, private key
   `/etc/pki/centreon-poller/tls.key` (CA certificate
   `/etc/pki/centreon-poller/ca.crt` if any).
4. Give the agents the CA in `ca_certificate`.

Poller-initiated (reverse) connections do not use this identity.

## Central-side prerequisites

- pullwss goes through the central's web server / ingress at
  `<central.baseUri>/gorgone/pullwss/websocket`. With ingress-nginx, raise
  `proxy-read-timeout` / `proxy-send-timeout`, otherwise idle websockets are
  cut after 60s.
- The broker port (5669) must be reachable from the poller's cluster.

## Values

| Key | Default | Notes |
|---|---|---|
| `poller.name` | — | Required. Poller name in Centreon |
| `poller.id` | — | Required. Add Poller `--uid` |
| `central.host` / `port` / `ssl` / `baseUri` | — / 443 / true / `/centreon` | pullwss endpoint |
| `secrets.existingSecret` | — | Required. Keys in `secrets.keys` |
| `images.engine` / `images.gorgone` | `ghcr.io/centreon/centreon-{engine,gorgone}:<appVersion>` | Testing: `docker.centreon.com/centreon/centreon-<c>-trixie` |
| `engine.addNetRaw` | `true` | `check_icmp`, see Security |
| `gorgone.extraEnv` / `engine.extraEnv` | `[]` | E.g. `SMTP_*` on the engine |
| `engine.otel.service` | ClusterIP 4317 | CMA agents; LoadBalancer for agents outside the cluster |
| `engine.otel.tls.existingSecret` | — | TLS identity for the agents, see CMA |
| `gracefulStop` | enabled, 60s | See below |
| `persistence.*` | see `values.yaml` | **Immutable after install** (volumeClaimTemplates) |

## Persistence

| Volume | Path | Why |
|---|---|---|
| `engine` | `/etc/centreon-engine` | Delivered engine config |
| `broker` | `/etc/centreon-broker` | Delivered cbmod config (not seeded) |
| `etc` | `/etc/centreon` | gorgone config |
| `logs` | `/var/log/centreon-engine` | Logs, `retention.dat` |
| `cma-pki` | `/etc/pki/centreon-engine` | Default CMA CA, used without `engine.otel.tls` |
| `cbmod-cache` | `/var/cache/centreon-engine` | cbmod queue saved on clean stop |
| `gorgone-data` | `/var/lib/centreon-gorgone` | gorgone keys and history |

The PVCs are kept on `helm uninstall` (Kubernetes never deletes
volumeClaimTemplate PVCs): reinstalling under the same release name keeps the
poller identity.

## Pod stop

On SIGTERM, centengine does not exit cleanly and is killed at the end of the
grace period, losing cbmod's in-memory queue. With `gracefulStop.enabled`,
gorgone's preStop sends the engine a gRPC shutdown (the path used by "restart"
from the central, which saves the queue) while the engine's preStop waits
`gracefulStop.waitSeconds`. Keep `terminationGracePeriodSeconds` above it.
Not validated on a cluster yet.

## Security

Pod Security Standards:

- `restricted`: not supported. Seed init containers run as root (chown of
  fresh PVCs) and the images run `sudo apt-get` at startup (plugin installs),
  so `allowPrivilegeEscalation: false` breaks them.
- `baseline`: only without the explicit `NET_RAW` add (`engine.addNetRaw: false`),
  on a runtime that keeps NET_RAW by default (containerd: k3s, EKS, GKE, AKS).
- With the defaults (`NET_RAW` added, needed on CRI-O), the namespace must be
  `privileged`.

The engine's gRPC port (50155) is unauthenticated and can shut the engine
down: restrict it with a NetworkPolicy.

## Known limitations

- Registration is manual (Add Poller); the chart never holds admin credentials.
- snmptrapd / centreontrapd and centreon-vmware are not covered yet.
- No NetworkPolicy shipped yet.
