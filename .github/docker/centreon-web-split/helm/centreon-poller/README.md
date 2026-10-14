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

The engine's default CA (`/etc/pki/centreon-engine/default_cma_ca.*`,
generated at first start and persisted) has CN = host name and no SAN. The
pod host name is `<release>-0` (forced by the StatefulSet controller), so it
never matches an external FQDN: agents with `encryption: full` must dial that
exact name.

An agent configuration delivered after the engine started needs an export
with **Restart** (a reload does not load the OpenTelemetry module).

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
| `persistence.*` | see `values.yaml` | **Immutable after install** (volumeClaimTemplates) |

## Persistence

| Volume | Path | Why |
|---|---|---|
| `engine` | `/etc/centreon-engine` | Delivered engine config |
| `broker` | `/etc/centreon-broker` | Delivered cbmod config (not seeded) |
| `etc` | `/etc/centreon` | gorgone config |
| `logs` | `/var/log/centreon-engine` | Logs, `retention.dat` |
| `cma-pki` | `/etc/pki/centreon-engine` | Default CMA CA |
| `engine-home` | `/var/lib/centreon-engine` | Engine user's home = cbmod's default cache directory (queue saved on clean stop). The command FIFO dir `rw/` is an emptyDir mounted over it |
| `gorgone-data` | `/var/lib/centreon-gorgone` | gorgone keys and history |

Back up at least `cma-pki` and `gorgone-data` (volume snapshots): losing
`cma-pki` makes the engine generate a **new** default CMA CA, so every agent
pinned on the old one (`-Fingerprint` / `ca_certificate`) must be
reconfigured. Losing `gorgone-data` regenerates the gorgone keys. The delivered
configuration (`engine`, `broker`) comes back with the next export.

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

## Node loss

A StatefulSet pod is never recreated elsewhere while Kubernetes cannot confirm
it stopped (at most one pod per identity): when its node dies, the pod is
evicted after ~5-6 min but stays bound to the dead node.

1. Make sure the node is really down (no split-brain with a running poller).
2. `kubectl delete pod <release>-0 --force --grace-period=0`, or taint the node
   `node.kubernetes.io/out-of-service=nodeshutdown:NoExecute` (also detaches
   its volumes).
3. The pod starts on another node only if its volumes can follow it: not with
   node-local storage (`local-path`); within the same zone with EBS-like
   block storage.
4. Every node must be able to pull every image (pull secret / registry
   credentials), or the restart stalls on `ImagePullBackOff`.

The in-memory part of cbmod's queue is lost; the on-disk part and the poller
identity are kept.

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

## Known limitations

- Registration is manual (Add Poller); the chart never holds admin credentials.
