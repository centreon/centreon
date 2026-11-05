{{/*
Pod spec of a poller: gorgone (TYPE=poller, pullwss through httpd) +
centengine. Shared by the Central poller (central-poller.yaml) and remote
pollers (pollers.yaml). Expects the caller to provide the volumes
poller-data (config and state, a directory per use: engine, etc, pki,
gorgone-data, traps-config, and broker for a remote poller), poller-spool
(what grows: centlog, cbmod-cache), poller-broker
(Central poller only: shared with the broker pod) and poller-centcmd; with
persistence.migrateLegacyVolumes also legacy-engine, legacy-etc,
legacy-centlog, legacy-cma-pki, legacy-cbmod-cache, legacy-traps-config
(traps) and legacy-broker (remote poller), the chart <= 0.40 volumes.
Call with (dict "ctx" $ "p" <poller dict>), p keys:
  name, uid, central (bool), timezone, gorgoneResources, engineResources,
  otelTlsSecret, otelTlsMountPath (optional: CMA TLS identity; the caller
  then provides the otel-tls-src Secret and otel-tls emptyDir volumes),
  traps (dict, optional: .enabled, .logLevel, .snmptrapdResources,
  .centreontrapdResources; the caller then provides the poller-traps-spool
  volume, the definitions are in poller-data), vmware (dict, optional: .enabled,
  .resources),
  tokenSecret {name,key} (remote only)
*/}}
{{- define "centreon-central.pollerPodSpec" -}}
{{- $ := .ctx -}}
{{- $p := .p -}}
{{- /* Poller ids are 18-digit nagios_server ids: as a YAML/JSON number Helm
       stores them as float64 (unquoted in a values file, or --set then
       --reuse-values), rendering "3.66e+17" and gorgone gets refused. */ -}}
{{- if not (or (kindIs "string" $p.uid) (kindIs "int64" $p.uid) (kindIs "int" $p.uid)) }}
{{- fail (printf "poller %q: id/uid must be a quoted string (use --set-string or quotes in values), got %s %v" $p.name (kindOf $p.uid) $p.uid) }}
{{- end }}
{{- $traps := and $p.traps $p.traps.enabled }}
{{- $vmware := and $p.vmware $p.vmware.enabled }}
{{- /* The Central poller's /etc/centreon-broker is shared with the broker
       pod (its own PVC), a remote poller's is a directory of poller-data. */ -}}
{{- $brokerMount := "{ name: poller-data, mountPath: /etc/centreon-broker, subPath: broker }" }}
{{- $seedBroker := "/seed/data/broker" }}
{{- if $p.central }}
{{- $brokerMount = "{ name: poller-broker, mountPath: /etc/centreon-broker }" }}
{{- $seedBroker = "/seed/broker" }}
{{- end }}
{{ include "centreon-central.podScheduling" $ }}
# gorgone reloads the engine with a gRPC call to "centengine:50155": it must
# reach this pod's own engine, never another poller's. The engine's VMware plugins reach
# the connector as "centreon-vmware" (same name as with install-poller), here
# in the same pod.
hostAliases:
  - ip: 127.0.0.1
    hostnames:
      - centengine
      {{- if $vmware }}
      - centreon-vmware
      {{- end }}
{{- if $traps }}
# snmptrapd runs as uid 900 and binds 162/udp: Docker allows it through its
# default unprivileged port range, Kubernetes through this sysctl ("safe",
# allowed by the baseline Pod Security Standard).
securityContext:
  sysctls:
    - { name: net.ipv4.ip_unprivileged_port_start, value: "162" }
{{- end }}
initContainers:
  {{- if $.Values.persistence.migrateLegacyVolumes }}
  # Upgrade from chart <= 0.40: the per-directory volumes into poller-data
  # and poller-spool. The CMA CA (pki) keeps its files: the agents pin its
  # fingerprint.
  - name: migrate-volumes
    image: {{ include "centreon-central.image" $.Values.images.engine }}
    imagePullPolicy: {{ $.Values.images.engine.pullPolicy }}
    securityContext:
      runAsUser: 0
    command: ["/bin/sh", "-c"]
    args:
      - |
        set -e
        {{- include "centreon-central.migrateFn" $ | nindent 8 }}
        migrate /old/engine /new/engine /new/.migrated/engine
        migrate /old/etc /new/etc /new/.migrated/etc
        migrate /old/centlog /spool/centlog /spool/.migrated/centlog
        migrate /old/pki /new/pki /new/.migrated/pki
        migrate /old/cbmod-cache /spool/cbmod-cache /spool/.migrated/cbmod-cache
        {{- if $traps }}
        migrate /old/traps-config /new/traps-config /new/.migrated/traps-config
        {{- end }}
        {{- if not $p.central }}
        migrate /old/broker /new/broker /new/.migrated/broker
        {{- end }}
    volumeMounts:
      - { name: poller-data, mountPath: /new }
      - { name: poller-spool, mountPath: /spool }
      - { name: legacy-engine, mountPath: /old/engine, readOnly: true }
      - { name: legacy-etc, mountPath: /old/etc, readOnly: true }
      - { name: legacy-centlog, mountPath: /old/centlog, readOnly: true }
      - { name: legacy-cma-pki, mountPath: /old/pki, readOnly: true }
      - { name: legacy-cbmod-cache, mountPath: /old/cbmod-cache, readOnly: true }
      {{- if $traps }}
      - { name: legacy-traps-config, mountPath: /old/traps-config, readOnly: true }
      {{- end }}
      {{- if not $p.central }}
      - { name: legacy-broker, mountPath: /old/broker, readOnly: true }
      {{- end }}
  {{- end }}
  # Docker named-volume copy-up made explicit: /etc/centreon-engine gets the
  # engine image's baked config (centengine.cfg, conf.d/...), the log dir its
  # archives/. /etc/centreon-broker is NOT seeded: the broker image refuses
  # its package-default config and waits for the delivered one. The
  # poller-data and poller-spool directories are created here, before the
  # containers mount them by subPath (kubelet would create a missing one
  # root-owned).
  - name: seed-engine
    image: {{ include "centreon-central.image" $.Values.images.engine }}
    imagePullPolicy: {{ $.Values.images.engine.pullPolicy }}
    securityContext:
      runAsUser: 0
    command: ["/bin/sh", "-c"]
    args:
      - |
        {{- include "centreon-central.seedFn" $ | nindent 8 }}
        mkdir -p /seed/data/engine /seed/spool/centlog /seed/data/pki /seed/spool/cbmod-cache {{ $seedBroker }}
        seed /etc/centreon-engine /seed/data/engine
        seed /var/log/centreon-engine /seed/spool/centlog
        # CMA CA (default_cma_ca.{crt,key}), generated by centengine at first
        # boot only if absent: persisted so the agents' pinned fingerprint
        # survives restarts. Seeding gives the dir the image's 901:901 0775,
        # otherwise a fresh volume is root-owned and centengine can't write it.
        seed /etc/pki/centreon-engine /seed/data/pki
        # gorgone (in group 902) writes the delivered config, centengine
        # and centreon-broker (both in 902) read it.
        own 902:902 775 {{ $seedBroker }}
        # External command FIFO dir (centengine creates the pipe, gorgone
        # writes commands into it).
        own 901:901 775 /seed/centcmd
        # cbmod's cache directory (not shipped by the image): its in-memory
        # queue is saved there on a clean stop and reloaded at start, and
        # overflows there during a long broker outage.
        own 901:901 775 /seed/spool/cbmod-cache
        {{- if $p.otelTlsSecret }}
        # CMA TLS identity: private to centengine (gorgone shares the pod).
        for f in /seed/otel-tls-src/*; do
          n=$(basename "$f")
          cp "$f" "/seed/otel-tls/$n"
          chown 901:901 "/seed/otel-tls/$n"
          case "$n" in *.key) chmod 600 "/seed/otel-tls/$n" ;; *) chmod 644 "/seed/otel-tls/$n" ;; esac
        done
        own 901:901 750 /seed/otel-tls
        {{- end }}
        ls -lnd /seed/data/* /seed/spool/* /seed/*
    volumeMounts:
      - { name: poller-data, mountPath: /seed/data }
      - { name: poller-spool, mountPath: /seed/spool }
      {{- if $p.central }}
      - { name: poller-broker, mountPath: /seed/broker }
      {{- end }}
      - { name: poller-centcmd, mountPath: /seed/centcmd }
      {{- if $p.otelTlsSecret }}
      - { name: otel-tls-src, mountPath: /seed/otel-tls-src, readOnly: true }
      - { name: otel-tls, mountPath: /seed/otel-tls }
      {{- end }}
  - name: seed-gorgone
    image: {{ include "centreon-central.image" $.Values.images.gorgone }}
    imagePullPolicy: {{ $.Values.images.gorgone.pullPolicy }}
    securityContext:
      runAsUser: 0
    command: ["/bin/sh", "-c"]
    args:
      - |
        {{- include "centreon-central.seedFn" $ | nindent 8 }}
        mkdir -p /seed/data/etc /seed/data/gorgone-data
        seed /etc/centreon /seed/data/etc
        # gorgone's history.sdb (action logs until gorgone-central fetches
        # them) and its own keys.
        seed /var/lib/centreon-gorgone /seed/data/gorgone-data
        {{- if $traps }}
        # Trap definitions (centreontrapd.sdb): written by gorgone (903),
        # read by centreontrapd (900).
        mkdir -p /seed/data/traps-config
        own 903:900 775 /seed/data/traps-config
        # Trap spool: snmptrapd writes, centreontrapd consumes.
        own 900:900 1777 /seed/traps-spool
        {{- end }}
        ls -lnd /seed/data/*
    volumeMounts:
      - { name: poller-data, mountPath: /seed/data }
      {{- if $traps }}
      - { name: poller-traps-spool, mountPath: /seed/traps-spool }
      {{- end }}
  {{- if $vmware }}
  # The connector's default config until the central delivers one (never
  # overwritten), in /etc/centreon shared with gorgone.
  - name: seed-vmware
    image: {{ include "centreon-central.vmwareImage" $ }}
    imagePullPolicy: {{ $.Values.images.vmware.pullPolicy }}
    securityContext:
      runAsUser: 0
    command: ["/bin/sh", "-c"]
    args:
      - |
        [ -e /seed/data/etc/centreon_vmware.json ] ||
          cp -p /etc/centreon/centreon_vmware.json /seed/data/etc/
        ls -ln /seed/data/etc/centreon_vmware.json
    volumeMounts:
      - { name: poller-data, mountPath: /seed/data }
  {{- end }}
  {{- with include "centreon-central.vaultInit" $ }}{{ . | nindent 2 }}{{ end }}
containers:
  - name: gorgone
    image: {{ include "centreon-central.image" $.Values.images.gorgone }}
    imagePullPolicy: {{ $.Values.images.gorgone.pullPolicy }}
    env:
      - { name: TYPE, value: poller }
      - { name: NAME, value: {{ $p.name | quote }} }
      {{- include "centreon-central.secretEnv" (dict "ctx" $ "name" "APP_SECRET" "key" "APP_SECRET") | nindent 6 }}
      {{- include "centreon-central.secretEnv" (dict "ctx" $ "name" "SALT" "key" "SALT") | nindent 6 }}
      - { name: GORGONE_UID, value: {{ $p.uid | quote }} }
      - { name: CENTRAL_HOST, value: httpd }
      - { name: CENTRAL_PORT, value: "80" }
      {{- if $p.central }}
      # Central's own poller fetches the "poller-default" token itself.
      - { name: PULLWSS_TOKEN_NAME, value: {{ $.Values.centralPoller.gorgone.pullwssTokenName | quote }} }
      - { name: PULLWSS_TOKEN_FETCH_RETRIES, value: {{ $.Values.centralPoller.gorgone.pullwssTokenFetchRetries | quote }} }
      - { name: CENTREON_API_BASE_URL, value: "http://httpd/centreon/api/latest/" }
      - { name: CENTREON_API_USERNAME, value: {{ $.Values.centreonApiUsername | quote }} }
      {{- include "centreon-central.secretEnv" (dict "ctx" $ "name" "CENTREON_API_PASSWORD" "key" "GORGONE_API_PASSWORD") | nindent 6 }}
      {{- else }}
      # Remote poller: static token from "Add Poller", never admin creds.
      - name: GORGONE_TOKEN
        valueFrom:
          secretKeyRef:
            name: {{ required "pollers[].tokenSecret.name is required" $p.tokenSecret.name }}
            key: {{ $p.tokenSecret.key | default "GORGONE_TOKEN" }}
      {{- end }}
      # httpd serves plain HTTP in-cluster (TLS, if any, ends at the ingress).
      - { name: GORGONE__GORGONE__MODULES__PULLWSS__SSL, value: "false" }
    volumeMounts:
      - { name: poller-data, mountPath: /etc/centreon-engine, subPath: engine }
      - {{ $brokerMount }}
      - { name: poller-centcmd, mountPath: /var/lib/centreon-engine/rw }
      - { name: poller-data, mountPath: /etc/centreon, subPath: etc }
      - { name: poller-data, mountPath: /var/lib/centreon-gorgone, subPath: gorgone-data }
      {{- if $traps }}
      - { name: poller-data, mountPath: /etc/snmp/centreon_traps, subPath: traps-config }
      {{- end }}
      {{- with include "centreon-central.vaultMounts" (dict "ctx" $ "collect" false) }}{{ . | nindent 6 }}{{ end }}
    {{- with $p.gorgoneResources }}
    resources:
      {{- toYaml . | nindent 6 }}
    {{- end }}
  - name: centengine
    image: {{ include "centreon-central.image" $.Values.images.engine }}
    imagePullPolicy: {{ $.Values.images.engine.pullPolicy }}
    # centengine exits without its cbmod config, which gorgone delivers on
    # the first export. gorgone runs in this pod, so an initContainer can't
    # wait for it: the container waits (files/centreon-central-wait-broker-
    # module-config.sh, the script compose mounts), then runs the image's
    # entrypoint with its command.
    command: ["/bin/sh", "-c", {{ printf "%s\nexec /usr/local/lib/centreon-engine/container.sh \"$@\"\n" (required "files/centreon-central-wait-broker-module-config.sh missing from the chart" ($.Files.Get "files/centreon-central-wait-broker-module-config.sh")) | toJson }}, "wait-broker-module-config"]
    args: ["/usr/sbin/centengine", "/etc/centreon-engine/centengine.cfg"]
    env:
      - { name: NAME, value: {{ $p.name | quote }} }
      - { name: TZ, value: {{ $p.timezone | default "UTC" | quote }} }
      - { name: DEBUG, value: {{ $.Values.centralPoller.debug | quote }} }
      {{- include "centreon-central.secretEnv" (dict "ctx" $ "name" "APP_SECRET" "key" "APP_SECRET") | nindent 6 }}
      {{- include "centreon-central.secretEnv" (dict "ctx" $ "name" "SALT" "key" "SALT") | nindent 6 }}
      - { name: ENGINE_PORT, value: {{ $.Values.centralPoller.engine.port | quote }} }
    ports:
      - { name: engine-grpc, containerPort: 50155 }
      # OpenTelemetry / Centreon Monitoring Agent (CMA) gRPC server, active
      # when an agent configuration is attached to the poller (otl_server.json).
      - { name: otel, containerPort: 4317 }
    # No probes.
    volumeMounts:
      - { name: poller-data, mountPath: /etc/centreon-engine, subPath: engine }
      - {{ $brokerMount }}
      - { name: poller-centcmd, mountPath: /var/lib/centreon-engine/rw }
      - { name: poller-spool, mountPath: /var/log/centreon-engine, subPath: centlog }
      - { name: poller-data, mountPath: /etc/pki/centreon-engine, subPath: pki }
      # Used once the poller's cbmod config has cache_directory pointing here
      # (Central: set by the install Job; remote pollers: "Cache directory" of
      # their broker configuration in Centreon).
      - { name: poller-spool, mountPath: {{ $.Values.centralPoller.cbmodCacheDirectory }}, subPath: cbmod-cache }
      {{- if $p.otelTlsSecret }}
      - { name: otel-tls, mountPath: {{ $p.otelTlsMountPath }}, readOnly: true }
      {{- end }}
      {{- with include "centreon-central.vaultMounts" (dict "ctx" $ "collect" true) }}{{ . | nindent 6 }}{{ end }}
    {{- with $p.engineResources }}
    resources:
      {{- toYaml . | nindent 6 }}
    {{- end }}
  {{- if $traps }}
  # SNMP traps: snmptrapd receives them on 162/udp and spools them,
  # centreontrapd matches them against the delivered definitions and submits
  # results to the engine's command FIFO (hence the same pod).
  # One trapd image for both containers: the role keyword is expanded to the
  # daemon's command line by the image (flags live there only).
  - name: snmptrapd
    image: {{ include "centreon-central.image" $.Values.images.snmptrapd }}
    imagePullPolicy: {{ $.Values.images.snmptrapd.pullPolicy }}
    args: ["snmptrapd"]
    env:
      - { name: TZ, value: {{ $p.timezone | default "UTC" | quote }} }
    ports:
      - { name: snmptrap, containerPort: 162, protocol: UDP }
    volumeMounts:
      - { name: poller-traps-spool, mountPath: /var/spool/centreontrapd }
    {{- with $p.traps.snmptrapdResources }}
    resources:
      {{- toYaml . | nindent 6 }}
    {{- end }}
  - name: centreontrapd
    image: {{ include "centreon-central.image" $.Values.images.centreontrapd }}
    imagePullPolicy: {{ $.Values.images.centreontrapd.pullPolicy }}
    # --severity comes from CENTREONTRAPD_LOG_LEVEL (image entrypoint).
    args: ["centreontrapd"]
    env:
      - { name: TZ, value: {{ $p.timezone | default "UTC" | quote }} }
      - { name: CENTREONTRAPD_LOG_LEVEL, value: {{ $p.traps.logLevel | default "error" | quote }} }
    volumeMounts:
      - { name: poller-traps-spool, mountPath: /var/spool/centreontrapd }
      # Read-only: only gorgone writes the definitions (as install-poller's
      # :ro). centreontrapd fails with "Read-only file system" only while
      # centreontrapd.sdb has not been delivered yet (SQLite tries to create it).
      - { name: poller-data, mountPath: /etc/snmp/centreon_traps, subPath: traps-config, readOnly: true }
      - { name: poller-centcmd, mountPath: /var/lib/centreon-engine/rw }
    {{- with $p.traps.centreontrapdResources }}
    resources:
      {{- toYaml . | nindent 6 }}
    {{- end }}
  {{- end }}
  {{- if $vmware }}
  # VMware connector, queried by the engine's VMware plugins on 5700. Its
  # config (/etc/centreon/centreon_vmware.json) is delivered by the central
  # through gorgone; the connector restarts itself on change.
  - name: centreon-vmware
    image: {{ include "centreon-central.vmwareImage" $ }}
    imagePullPolicy: {{ $.Values.images.vmware.pullPolicy }}
    env:
      - { name: TZ, value: {{ $p.timezone | default "UTC" | quote }} }
      {{- include "centreon-central.secretEnv" (dict "ctx" $ "name" "APP_SECRET" "key" "APP_SECRET") | nindent 6 }}
      {{- include "centreon-central.secretEnv" (dict "ctx" $ "name" "SALT" "key" "SALT") | nindent 6 }}
    ports:
      - { name: vmware, containerPort: 5700 }
    volumeMounts:
      - { name: poller-data, mountPath: /etc/centreon, subPath: etc }
    {{- with $p.vmware.resources }}
    resources:
      {{- toYaml . | nindent 6 }}
    {{- end }}
  {{- end }}
{{- end -}}

{{/* Traps Service of a poller: (dict "ctx" $ "name" <svc name> "component" <component> "svc" <traps.service>) */}}
{{- define "centreon-central.trapsService" -}}
{{- $ := .ctx -}}
apiVersion: v1
kind: Service
metadata:
  # SNMP traps (162/udp). centreontrapd matches the sender by source IP: keep
  # externalTrafficPolicy Local on a LoadBalancer (Cluster SNATs it).
  name: {{ .name }}
  labels:
    {{- include "centreon-central.labels" $ | nindent 4 }}
    app.kubernetes.io/component: {{ .component }}
  {{- with .svc.annotations }}
  annotations:
    {{- toYaml . | nindent 4 }}
  {{- end }}
spec:
  {{- include "centreon-central.serviceSpecType" .svc | nindent 2 }}
  selector:
    {{- include "centreon-central.selectorLabels" (dict "ctx" $ "component" .component) | nindent 4 }}
  ports:
    - name: snmptrap
      protocol: UDP
      port: {{ .svc.port | default 162 }}
      targetPort: snmptrap
      {{- if and .svc.nodePort (ne (.svc.type | default "ClusterIP") "ClusterIP") }}
      nodePort: {{ .svc.nodePort }}
      {{- end }}
{{- end -}}
