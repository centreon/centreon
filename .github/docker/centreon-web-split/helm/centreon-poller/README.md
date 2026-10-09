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
2. In the poller's broker configuration in Centreon, set the output host to
   the central broker endpoint reachable from this cluster (raw TCP 5669,
   LoadBalancer or NodePort — not an Ingress). Keep the default "Cache
   directory": `/var/lib/centreon-engine` is persisted.
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
     --set-string poller.id=<uid> `
     --set central.host=centreon.example.com `
     --set secrets.existingSecret=poller-1
   ```

5. Export the poller configuration from the central.

`centengine` crash-loops until the first configuration lands: expected. The
kubelet's restart back-off grows up to 5 minutes, so a poller installed long
before its first export can take that long to pick it up (`kubectl delete pod`
to apply it at once). The same back-off delays a "restart" from the central
(a clean exit) until the engine has run 10 minutes without restarting.

## Centreon Monitoring Agents (CMA)

Agents dial the poller on 4317 (`engine.otel.service`, LoadBalancer for agents
outside the cluster). With `encryption: full` they check the poller
certificate against the FQDN of their `endpoint`.

The engine's default CA (`/etc/pki/centreon-engine/default_cma_ca.*`) has
CN = host name and no SAN. The pod host name is `<release>-0` (forced by the
StatefulSet controller), so it never matches an external FQDN. Provide the
TLS identity instead:

1. Get a certificate with the FQDN agents dial as SAN, either:
   - **cert-manager** (recommended): `engine.otel.tls.certManager.enabled=true`,
     `dnsNames` = the FQDN(s), `issuerRef` = your Issuer / ClusterIssuer
     (e.g. a CA Issuer whose CA the agents trust). The chart creates the
     `Certificate`; cert-manager writes the Secret `<release>-otel-tls` and
     renews it.
   - or a `kubernetes.io/tls` Secret of yours (`tls.crt`, `tls.key`, optional
     `ca.crt`): `engine.otel.tls.existingSecret=<secret>`. A self-signed CA
     also works: the engine issues its server certificates from it.

   The CA's subject must differ from the server certificate's (a CA named
   after the FQDN makes the server certificate look self-signed to agents).
2. The init container copies the Secret to `/etc/pki/centreon-poller`, owned
   by centengine (key `0600`, not readable by gorgone). A renewed certificate
   is picked up at the next pod restart.
3. In the poller's agent configuration in Centreon: public certificate
   `/etc/pki/centreon-poller/tls.crt`, private key
   `/etc/pki/centreon-poller/tls.key` (CA certificate
   `/etc/pki/centreon-poller/ca.crt` if any), then export with **Restart**
   (a reload does not load the OpenTelemetry module).
4. Give the agents the CA in `ca_certificate` (`install_cma.ps1 -CA <file>`).

Poller-initiated (reverse) connections do not use this identity.

## SNMP traps

`--set traps.enabled=true` adds `snmptrapd` (receives traps on 162/udp, spools
them) and `centreontrapd` (matches them against the definitions delivered by
the central, submits results to the engine's command FIFO) to the poller pod,
plus a `traps-config` volume (`/etc/snmp/centreon_traps`) and a
`<release>-snmptrap` Service (LoadBalancer, UDP 162).

- Generate and apply the SNMP traps database for the poller from the central
  (Configuration > SNMP Traps > Generate). `centreontrapd` crash-loops until
  `centreontrapd.sdb` is delivered, logging "Can't open the file
  .../centreontrapd.sdb: Read-only file system" (the definitions are
  read-only for it, only gorgone writes them).
- With `traps.logLevel=info` or `debug`, "Duplicate trap detected ...
  Skipping" also shows up for matched traps (second pass while the result is
  submitted): it does not mean the host is unknown.
- `centreontrapd` matches the sender by source IP: the load balancer must keep
  it. `externalTrafficPolicy: Local` (default) does with cloud load balancers
  and MetalLB; k3s' built-in ServiceLB (klipper) does not.
- `snmptrapd` runs as uid 900: the pod gets the
  `net.ipv4.ip_unprivileged_port_start=162` sysctl (allowed by `baseline`).
- Enabling traps on an installed poller changes the volume list (see
  Persistence for the upgrade procedure).

## VMware connector

`--set vmware.enabled=true` adds `centreon-vmware` to the poller pod. The
engine's VMware plugins reach it as `centreon-vmware:5700` (host alias to the
pod itself), its configuration (`/etc/centreon/centreon_vmware.json`) is
delivered by the central through gorgone, and it restarts on each update.

There is no public image: it embeds the VMware Perl SDK, under a Broadcom
license. Build it from a `centreon-plugins` checkout with the SDK archives in
`sdks-vmware/` (see its README), push it to a **private** registry and give
the chart its reference and pull secret:

```powershell
docker build -f .github/docker/connector/Dockerfile.connector-vmware `
  -t registry.example.com/centreon/connector-vmware:<version> .
docker push registry.example.com/centreon/connector-vmware:<version>

helm upgrade poller-1 . -n pollers --reset-then-reuse-values `
  --set vmware.enabled=true `
  --set vmware.image.repository=registry.example.com/centreon/connector-vmware `
  --set vmware.image.tag=<version> `
  --set "imagePullSecrets[0].name=<registry-secret>"
```

Enabling it does not change the volume list.

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
| `poller.id` | — | Required. Add Poller `--uid` as a string: `--set-string`, or quoted in a values file (unquoted, it is parsed as a float and loses precision) |
| `central.host` / `port` / `ssl` / `baseUri` | — / 443 / true / `/centreon` | pullwss endpoint |
| `secrets.existingSecret` | — | Required. Keys in `secrets.keys` |
| `images.engine` / `images.gorgone` | `ghcr.io/centreon/centreon-{engine,gorgone}:<appVersion>` | Testing: `docker.centreon.com/centreon/centreon-<c>-trixie` |
| `engine.addNetRaw` | `false` | `true` on CRI-O / OpenShift for `check_icmp`, see Security |
| `gorgone.extraEnv` / `engine.extraEnv` | `[]` | E.g. `SMTP_*` on the engine |
| `engine.otel.service` | ClusterIP 4317 | CMA agents; LoadBalancer for agents outside the cluster |
| `engine.otel.tls.existingSecret` | — | TLS identity for the agents, see CMA |
| `engine.otel.tls.certManager` | disabled | Certificate issued by cert-manager (`dnsNames`, `issuerRef`), see CMA |
| `traps.enabled` | `false` | snmptrapd + centreontrapd, see SNMP traps |
| `traps.service` | LoadBalancer UDP 162, `externalTrafficPolicy: Local` | Keeps the trap source IP |
| `vmware.enabled` / `vmware.image` | `false` / — | Image required, built with the licensed SDK, see VMware connector |
| `networkPolicy.enabled` / `otelFrom` / `trapsFrom` | `true` / anywhere / anywhere | Ingress limited to 4317/tcp and 162/udp, see Security |
| `persistence.*` | see `values.yaml` | **Immutable after install** (volumeClaimTemplates) |

## Persistence

| Volume | Path | Why |
|---|---|---|
| `engine` | `/etc/centreon-engine` | Delivered engine config |
| `broker` | `/etc/centreon-broker` | Delivered cbmod config (not seeded) |
| `etc` | `/etc/centreon` | gorgone config |
| `logs` | `/var/log/centreon-engine` | Logs, `retention.dat` |
| `cma-pki` | `/etc/pki/centreon-engine` | Default CMA CA, used without `engine.otel.tls` |
| `engine-home` | `/var/lib/centreon-engine` | Engine user's home = cbmod's default cache directory (queue saved on clean stop). The command FIFO dir `rw/` is an emptyDir mounted over it |
| `gorgone-data` | `/var/lib/centreon-gorgone` | gorgone keys and history |

Upgrades: use `--reset-then-reuse-values` rather than `--reuse-values`, which
ignores new chart defaults (e.g. a new volume size). A change to the volume
list needs `kubectl delete sts <release> --cascade=orphan` before the upgrade,
then `kubectl delete pod <release>-0`.

The PVCs are kept on `helm uninstall` (Kubernetes never deletes
volumeClaimTemplate PVCs): reinstalling under the same release name keeps the
poller identity.

## Pod stop

On SIGTERM (rollout, drain, `kubectl delete pod`), centengine stops cleanly
in ~15 s: it writes `retention.dat` and cbmod saves its queue to its cache
directory, reloaded at the next start (validated with the broker unreachable:
the new pod "starts with 238 in queue"). `terminationGracePeriodSeconds`
(60 s) leaves room for it. An abrupt death (node loss, OOM kill) still loses
the in-memory part of the queue.

Persisting `/var/lib/centreon-engine` requires an engine image whose
entrypoint scripts are not in that directory (MON-211751); with an older image
they would be frozen on the volume at their first-install version.

## Security

Pod Security Standards:

- `restricted`: not supported. Seed init containers run as root (chown of
  fresh PVCs) and the images run `sudo apt-get` at startup (plugin installs),
  so `allowPrivilegeEscalation: false` breaks them.
- `baseline`: supported with the defaults. `check_icmp` needs NET_RAW, which
  containerd grants by default (k3s, EKS, GKE, AKS).
- CRI-O / OpenShift drop NET_RAW: set `engine.addNetRaw: true`, which requires
  a `privileged` namespace.

NetworkPolicy (`networkPolicy.enabled`, default on, needs an enforcing CNI):
only 4317/tcp (CMA) and 162/udp (traps, when enabled) are reachable from
outside the pod; restrict their sources with `networkPolicy.otelFrom` /
`trapsFrom`. The engine's gRPC API (50155, unauthenticated, can shut the
engine down) and the VMware connector (5700) are only reachable from inside
the pod. Egress is not restricted (central, broker, monitored hosts).

## Known limitations

- Registration is manual (Add Poller); the chart never holds admin credentials.
