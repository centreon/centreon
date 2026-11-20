{{- define "centreon-central.name" -}}
{{- .Chart.Name | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{- define "centreon-central.labels" -}}
helm.sh/chart: {{ printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" }}
app.kubernetes.io/name: {{ include "centreon-central.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end -}}

{{/* Selector labels: call with (dict "ctx" $ "component" "xxx") */}}
{{- define "centreon-central.selectorLabels" -}}
app.kubernetes.io/name: {{ include "centreon-central.name" .ctx }}
app.kubernetes.io/instance: {{ .ctx.Release.Name }}
app.kubernetes.io/component: {{ .component }}
{{- end -}}

{{- define "centreon-central.image" -}}
{{- printf "%s:%s" .repository (toString .tag) -}}
{{- with .digest }}@{{ . }}{{ end -}}
{{- end -}}

{{- define "centreon-central.secretName" -}}
{{- default (printf "%s-secrets" .Release.Name) .Values.secrets.existingSecret -}}
{{- end -}}

{{- define "centreon-central.dbHost" -}}
{{- if .Values.database.bundled -}}db{{- else -}}{{ required "database.externalHost is required when database.bundled=false" .Values.database.externalHost }}{{- end -}}
{{- end -}}

{{- define "centreon-central.dbPort" -}}
{{- .Values.database.port | default 3306 | int -}}
{{- end -}}

{{/* env var from the shared Secret: (dict "ctx" $ "name" "ENV" "key" "KEY") */}}
{{- define "centreon-central.secretEnv" -}}
- name: {{ .name }}
  valueFrom:
    secretKeyRef:
      name: {{ include "centreon-central.secretName" .ctx }}
      key: {{ .key }}
{{- end -}}

{{- define "centreon-central.pvcName" -}}
{{- printf "%s-%s" .ctx.Release.Name .name -}}
{{- end -}}

{{/* Apache ServerName (UseCanonicalName On builds every self-referential
     URL from it, incl. the /centreon -> /centreon/ redirect). Explicit value
     wins; otherwise the ingress host, with an https:// scheme when the
     ingress terminates TLS (Apache accepts a scheme in ServerName, so
     redirects don't fall back to http://). */}}
{{- define "centreon-central.serverName" -}}
{{- if .Values.httpd.serverName -}}
{{- .Values.httpd.serverName -}}
{{- else if .Values.ingress.enabled -}}
{{- if .Values.ingress.tls }}https://{{ end }}{{ required "ingress.host is required when ingress.enabled" .Values.ingress.host }}
{{- else -}}
localhost
{{- end -}}
{{- end -}}

{{/* type/nodePort/loadBalancer fields of an exposable Service: call with a
     service values dict and the port name nodePort applies to. */}}
{{- define "centreon-central.serviceSpecType" -}}
type: {{ .type | default "ClusterIP" }}
{{- if and (eq (.type | default "ClusterIP") "LoadBalancer") .loadBalancerSourceRanges }}
loadBalancerSourceRanges:
  {{- toYaml .loadBalancerSourceRanges | nindent 2 }}
{{- end }}
{{- if and (ne (.type | default "ClusterIP") "ClusterIP") .externalTrafficPolicy }}
externalTrafficPolicy: {{ .externalTrafficPolicy }}
{{- end }}
{{- end -}}

{{/* Identifies one install run: changes with the fpm image (upgrade), the
     chart version, runId or the install's environment. The chart version is
     in it because a Job's pod template is immutable: a chart change to the
     Jobs under the same name would make `helm upgrade` fail. The environment
     is in it so that a values-only change (e.g. rrdcached.enabled) re-runs the
     install AND makes the config-delivery Job wait for it, instead of
     delivering the config before the install updated the database. */}}
{{- define "centreon-central.installId" -}}
{{- printf "%s|%s|%s|%s" (include "centreon-central.image" .Values.images.fpm) .Chart.Version (toString .Values.install.runId) (include "centreon-central.fpmInstallEnv" . | sha256sum) | sha256sum | trunc 10 -}}
{{- end -}}

{{/* rrdcached health: PING answered with PONG (bash only, no client in the
     image). */}}
{{- define "centreon-central.rrdcachedPing" -}}
["bash", "-c", "exec 3<>/dev/tcp/127.0.0.1/42217 && echo PING >&3 && read -t 2 r <&3 && [[ \"$r\" == *PONG* ]]"]
{{- end -}}

{{/* initContainer waiting for the install Job's marker (options.helm_install_id
     = this release's install id), as root: used by fpm, the autodiscovery
     worker and the config-delivery Job. */}}
{{- define "centreon-central.waitInstall" -}}
{{ include "centreon-central.waitInstallAs" (dict "ctx" . "user" "root" "passwordKey" "MYSQL_ROOT_PASSWORD") }}
{{- end -}}

{{/* waitInstall as a given database user: (dict "ctx" $ "user" <user>
     "passwordKey" <Secret key>). As "centreon" (CronJobs) the query also
     fails until the install Job has granted that user its database, and no
     root password reaches the pod. */}}
{{- define "centreon-central.waitInstallAs" -}}
{{- $ := .ctx -}}
- name: wait-install
  image: {{ include "centreon-central.image" $.Values.images.fpm }}
  imagePullPolicy: {{ $.Values.images.fpm.pullPolicy }}
  command: ["/bin/sh", "-c"]
  args:
    - |
      until [ "$(mysql {{ include "centreon-central.dbCliTls" $ }} -N -s -h"$MYSQL_HOST" -P"$MYSQL_PORT" -u"$MYSQL_USER" "$MYSQL_DB_CONFIGURATION" \
          -e "SELECT value FROM options WHERE \`key\` = 'helm_install_id'" 2>/dev/null)" = "$INSTALL_ID" ]; do
        echo "Waiting for install $INSTALL_ID..."
        sleep 5
      done
      echo "Install $INSTALL_ID done."
  env:
    - { name: MYSQL_HOST, value: {{ include "centreon-central.dbHost" $ | quote }} }
    - { name: MYSQL_PORT, value: {{ include "centreon-central.dbPort" $ | quote }} }
    - { name: MYSQL_DB_CONFIGURATION, value: {{ $.Values.database.configurationDb | quote }} }
    - { name: MYSQL_USER, value: {{ .user | quote }} }
    - { name: INSTALL_ID, value: {{ include "centreon-central.installId" $ | quote }} }
    {{- include "centreon-central.secretEnv" (dict "ctx" $ "name" "MYSQL_PWD" "key" .passwordKey) | nindent 4 }}
  {{- with include "centreon-central.dbTlsMounts" $ }}
  volumeMounts:
    {{- . | nindent 4 }}
  {{- end }}
{{- end -}}

{{/* Where the web (fpm, cron) reaches gorgone-central's API: overrides
     options gorgone_api_* (ConfigurationLoader). The credentials stay in the
     options (install Job). */}}
{{- define "centreon-central.gorgoneApiEnv" -}}
- { name: CENTREON_GORGONE_API_ADDRESS, value: gorgone-central }
- { name: CENTREON_GORGONE_API_PORT, value: "8085" }
- { name: CENTREON_GORGONE_API_SSL, value: "0" }
{{- end -}}

{{/* Common env of every container running the fpm image's install script. */}}
{{- define "centreon-central.fpmInstallEnv" -}}
- { name: RUN_INSTALL, value: "true" }
- { name: MYSQL_HOST, value: {{ include "centreon-central.dbHost" . | quote }} }
- { name: MYSQL_PORT, value: {{ include "centreon-central.dbPort" . | quote }} }
- { name: MYSQL_DB_CONFIGURATION, value: {{ .Values.database.configurationDb | quote }} }
- { name: MYSQL_DB_STORAGE, value: {{ .Values.database.storageDb | quote }} }
{{ include "centreon-central.secretEnv" (dict "ctx" . "name" "MYSQL_ROOT_PASSWORD" "key" "MYSQL_ROOT_PASSWORD") }}
# "centreon" DB user's password: replaces the install fixture's (database.json).
{{ include "centreon-central.secretEnv" (dict "ctx" . "name" "MYSQL_PASSWORD" "key" "MYSQL_PASSWORD") }}
{{ include "centreon-central.secretEnv" (dict "ctx" . "name" "APP_SECRET" "key" "APP_SECRET") }}
{{ include "centreon-central.secretEnv" (dict "ctx" . "name" "SALT" "key" "SALT") }}
- { name: PHP_TIMEZONE, value: {{ .Values.fpm.phpTimezone | default .Values.timezone | quote }} }
- { name: CENTREON_INTERNAL_API_BASE_URL, value: "http://httpd:80" }
{{ include "centreon-central.gorgoneApiEnv" . }}
# Config delivery is the config-delivery Job's (one-shot), and the broker
# pod owns the RRD directories.
- { name: RUN_CONFIG_DELIVERY, value: "false" }
# Credentials applied by the install Job: centreon-gorgone on every run,
# admin only at the first install (optional key).
{{ include "centreon-central.secretEnv" (dict "ctx" . "name" "GORGONE_API_PASSWORD" "key" "GORGONE_API_PASSWORD") }}
# Same service account for the web / config-delivery -> gorgone-central API.
- { name: GORGONE_API_USERNAME, value: {{ .Values.centreonApiUsername | quote }} }
- name: ADMIN_PASSWORD
  valueFrom:
    secretKeyRef:
      name: {{ include "centreon-central.secretName" . }}
      key: ADMIN_PASSWORD
      optional: true
- { name: ADMIN_PASSWORD_EXPIRED, value: {{ .Values.install.adminPasswordExpired | quote }} }
# Central cbmod's cache_directory, set by the install Job (persistent volume
# in the Central poller pod).
- { name: CBMOD_CACHE_DIRECTORY, value: {{ .Values.centralPoller.cbmodCacheDirectory | quote }} }
# Same for the master broker (persistent volume in the broker pod).
- { name: BROKER_CACHE_DIRECTORY, value: {{ .Values.broker.cacheDirectory | quote }} }
- { name: RRD_VOLUMES_CHOWN, value: "false" }
# rrdcached sidecar of the broker pod: the install Job switches the central
# rrd output to it (empty: disable), fpm flushes it before drawing a graph.
- { name: RRDCACHED_PORT, value: {{ ternary "42217" "" .Values.rrdcached.enabled | quote }} }
# Host the central rrd output connects to (G9 broker "host" key): the
# sidecar, in cbd's own pod (IPv4: it listens on 0.0.0.0). Explicit, the
# install's default is compose's separate "rrdcached" service.
- { name: RRDCACHED_HOST, value: {{ ternary "127.0.0.1" "" .Values.rrdcached.enabled | quote }} }
- { name: CENTREON_RRDCACHED_HOST, value: {{ ternary "centreon-rrdcached" "" .Values.rrdcached.enabled | quote }} }
{{- include "centreon-central.dbTlsEnv" . }}
- { name: DEBUG, value: {{ .Values.fpm.debug | quote }} }
{{- end -}}

{{/* Seed a volume from the image's own path, like Docker's named-volume copy-up.
     chown/chmod are non-fatal: some RWX backends (EFS access points) refuse them. */}}
{{- define "centreon-central.seedFn" -}}
seed() {
  cp -an "$1/." "$2/" || echo "WARN: could not copy $1 into $2"
  chown --reference="$1" "$2" || echo "WARN: could not chown $2"
  chmod --reference="$1" "$2" || echo "WARN: could not chmod $2"
}
own() {
  chown "$1" "$3" || echo "WARN: could not chown $3"
  chmod "$2" "$3" || echo "WARN: could not chmod $3"
}
{{- end -}}

{{/* "true" when the old licence volume is to be migrated: it only exists
     since chart 0.34. With cluster access, only if that PVC exists (a release
     from 0.33 or earlier has none: rendering it would leave fpm Pending on an
     unbound claim); without access (ArgoCD, helm template), assumed. */}}
{{- define "centreon-central.legacyLicense" -}}
{{- $pvc := include "centreon-central.pvcName" (dict "ctx" . "name" "centreon-license") -}}
{{- if or (not (lookup "v1" "Namespace" "" .Release.Namespace)) (lookup "v1" "PersistentVolumeClaim" .Release.Namespace $pvc) -}}
true
{{- end -}}
{{- end -}}

{{/* Copy of an old per-directory volume (chart <= 0.40) into its directory of
     a consolidated volume, for persistence.migrateLegacyVolumes. Once per
     target (marker under the volume root, outside every subPath), refuses a
     too small target, then checks every source file is in the target with
     the same size: any mismatch exits 1 and keeps the pod from starting on a
     partial copy. migrate <old dir> <new dir> <marker>. */}}
{{- define "centreon-central.migrateFn" -}}
migrate() {
  if [ -e "$3" ]; then echo "migrate: $2 already done"; return 0; fi
  [ -d "$1" ] || { echo "migrate: source $1 missing" >&2; exit 1; }
  need=$(du -sk "$1" | cut -f1)
  avail=$(df -Pk "$(dirname "$2")" | awk 'NR==2 {print $4}')
  [ "$need" -le "$avail" ] || { echo "migrate: $2 needs ${need} KiB, ${avail} KiB free" >&2; exit 1; }
  mkdir -p "$2"
  cp -a "$1/." "$2/"
  (cd "$1" && find . -type f -printf '%P %s\n' | sort) > /tmp/migrate-src
  (cd "$2" && find . -type f -printf '%P %s\n' | sort) > /tmp/migrate-dst
  bad=$(comm -23 /tmp/migrate-src /tmp/migrate-dst | wc -l)
  if [ "$bad" -ne 0 ]; then
    echo "migrate: $bad file(s) of $1 missing or different in $2" >&2
    comm -23 /tmp/migrate-src /tmp/migrate-dst | head -20 >&2
    exit 1
  fi
  echo "migrate: $1 -> $2 verified ($(wc -l < /tmp/migrate-src) files, ${need} KiB)"
  mkdir -p "$(dirname "$3")" && touch "$3"
}
{{- end -}}

{{/* "true" when fpm can run several replicas: the generated config is then
     shared (the UI generates it and moves it in two requests that may reach
     two replicas), otherwise it is pod-local. */}}
{{- define "centreon-central.fpmScaled" -}}
{{- if or .Values.fpm.autoscaling.enabled (gt (int .Values.fpm.replicas) 1) }}true{{ end -}}
{{- end -}}

{{/* Spread replicas of a scalable workload over nodes (soft: never blocks
     scheduling on a single-node cluster). Call with (dict "ctx" $ "component" "x"). */}}
{{- define "centreon-central.topologySpread" -}}
topologySpreadConstraints:
  - maxSkew: 1
    topologyKey: kubernetes.io/hostname
    whenUnsatisfiable: ScheduleAnyway
    labelSelector:
      matchLabels:
        {{- include "centreon-central.selectorLabels" . | nindent 8 }}
{{- end -}}

{{- define "centreon-central.podScheduling" -}}
{{- with .Values.nodeSelector }}
nodeSelector:
  {{- toYaml . | nindent 2 }}
{{- end }}
{{- with .Values.tolerations }}
tolerations:
  {{- toYaml . | nindent 2 }}
{{- end }}
{{- with .Values.affinity }}
affinity:
  {{- toYaml . | nindent 2 }}
{{- end }}
{{- with .Values.imagePullSecrets }}
imagePullSecrets:
  {{- toYaml . | nindent 2 }}
{{- end }}
{{- end -}}

{{/* Secret holding the Central poller's CMA TLS identity, empty if none. */}}
{{- define "centreon-central.otelTlsSecret" -}}
{{- if .Values.centralPoller.otel.tls.existingSecret -}}
{{- .Values.centralPoller.otel.tls.existingSecret -}}
{{- else if .Values.centralPoller.otel.tls.certManager.enabled -}}
{{- printf "%s-central-otel-tls" .Release.Name -}}
{{- end -}}
{{- end -}}

{{/* VMware connector image: no public image (licensed VMware Perl SDK). */}}
{{- define "centreon-central.vmwareImage" -}}
{{- $i := .Values.images.vmware -}}
{{- printf "%s:%s" (required "images.vmware.repository is required with vmware.enabled (no public image, see README)" $i.repository) (toString (required "images.vmware.tag is required with vmware.enabled" $i.tag)) -}}
{{- end -}}

{{/* Vault (vault.enabled): every pod that reads Vault generates its own
     runtime files at startup into an in-memory emptyDir (vault-config.sh on
     the fpm image), from values + the Secret: no volume shared between
     components, and a Vault configuration changed in the UI does not survive
     a restart (change the values instead). */}}
{{- define "centreon-central.vaultInit" -}}
{{- if .Values.vault.enabled }}
- name: vault-config
  image: {{ include "centreon-central.image" .Values.images.fpm }}
  imagePullPolicy: {{ .Values.images.fpm.pullPolicy }}
  securityContext:
    runAsUser: 0
  command: ["/usr/local/lib/centreon-fpm/vault-config.sh", "/vault-runtime"]
  env:
    - { name: VAULT_ADDRESS, value: {{ required "vault.address is required with vault.enabled" .Values.vault.address | quote }} }
    - { name: VAULT_PORT, value: {{ .Values.vault.port | quote }} }
    - { name: VAULT_ROOT_PATH, value: {{ .Values.vault.rootPath | quote }} }
    {{- include "centreon-central.secretEnv" (dict "ctx" . "name" "VAULT_ROLE_ID" "key" "VAULT_ROLE_ID") | nindent 4 }}
    {{- include "centreon-central.secretEnv" (dict "ctx" . "name" "VAULT_SECRET_ID" "key" "VAULT_SECRET_ID") | nindent 4 }}
    {{- include "centreon-central.secretEnv" (dict "ctx" . "name" "APP_SECRET" "key" "APP_SECRET") | nindent 4 }}
    {{- with .Values.vault.caCert }}
    - name: VAULT_CA_CERT
      value: {{ . | quote }}
    {{- end }}
  volumeMounts:
    - { name: vault-runtime, mountPath: /vault-runtime }
{{- end }}
{{- end -}}

{{- define "centreon-central.vaultVolume" -}}
{{- if .Values.vault.enabled }}
- name: vault-runtime
  emptyDir:
    medium: Memory
{{- end }}
{{- end -}}

{{/* (dict "ctx" $ "collect" bool): vault.json where the web, gorgone and the
     broker JSON expect it, the CA bundle trusting Vault, and for cbd/cbmod
     (collect) the APP_SECRET line they read from /usr/share/centreon/.env. */}}
{{- define "centreon-central.vaultMounts" -}}
{{- if .ctx.Values.vault.enabled }}
- { name: vault-runtime, mountPath: /var/lib/centreon/vault, readOnly: true }
- { name: vault-runtime, mountPath: /etc/ssl/certs/ca-certificates.crt, subPath: ca-certificates.crt, readOnly: true }
{{- if .collect }}
- { name: vault-runtime, mountPath: /usr/share/centreon/.env, subPath: app.env, readOnly: true }
{{- end }}
{{- end }}
{{- end -}}

{{/* Database TLS (database.tls): the CA is mounted at the same path in every
     pod, since the web writes that path into the broker's JSON (db_ssl_ca).
     All these render nothing with TLS off: unchanged behavior. */}}
{{- define "centreon-central.dbTlsCaPath" -}}/etc/centreon-db-tls/ca.pem{{- end -}}

{{/* Read by the fpm and cron images: written into .env.local.php for the web's
     PHP (DatabaseTLSResolver, broker generator) and turned into the mysql
     client's [client] options. 1/0 only: PHP's (bool) reads "false" as true. */}}
{{- define "centreon-central.dbTlsEnv" -}}
{{- $tls := .Values.database.tls -}}
{{- if $tls.enabled }}
{{- if and $tls.verifyServerCert (not $tls.caCert) }}
{{- fail "database.tls.caCert is required with database.tls.verifyServerCert (e.g. the AWS RDS global-bundle.pem)" }}
{{- end }}
- { name: DATABASE_SSL_ENABLED, value: "1" }
- { name: DATABASE_VERIFY_SERVER_CERT, value: {{ ternary "1" "0" (and $tls.verifyServerCert true) | quote }} }
{{- if $tls.caCert }}
- { name: DATABASE_CA_PATH, value: {{ include "centreon-central.dbTlsCaPath" . | quote }} }
{{- end }}
{{- end }}
{{- end -}}

{{- define "centreon-central.dbTlsVolume" -}}
{{- if and .Values.database.tls.enabled .Values.database.tls.caCert }}
- name: db-tls-ca
  configMap:
    name: {{ .Release.Name }}-db-ca
{{- end }}
{{- end -}}

{{- define "centreon-central.dbTlsMounts" -}}
{{- if and .Values.database.tls.enabled .Values.database.tls.caCert }}
- { name: db-tls-ca, mountPath: {{ include "centreon-central.dbTlsCaPath" . | dir }}, readOnly: true }
{{- end }}
{{- end -}}

{{/* mysql/mariadb CLI options. Off: --skip-ssl (the 11.x client requires
     TLS by default, the bundled server runs without it). */}}
{{- define "centreon-central.dbCliTls" -}}
{{- $tls := .Values.database.tls -}}
{{- if not $tls.enabled -}}
--skip-ssl
{{- else if $tls.verifyServerCert -}}
--ssl --ssl-ca={{ include "centreon-central.dbTlsCaPath" . }} --ssl-verify-server-cert
{{- else -}}
--ssl --disable-ssl-verify-server-cert
{{- end -}}
{{- end -}}

{{/* gorgone's DBI DSN attributes (DBD::mysql 4.053 in the image:
     mysql_ssl_ca_file, mysql_ssl_ca is ignored). mysql_ssl_optional=1: built
     on libmariadb, mysql_ssl=1 alone fails with "Enforcing SSL encryption is
     not supported"; the session is still TLS (the CA is checked, a wrong one
     is refused) and a server requiring TLS refuses anything else. */}}
{{- define "centreon-central.dbTlsDsn" -}}
{{- $tls := .Values.database.tls -}}
{{- if $tls.enabled -}}
;mysql_ssl=1;mysql_ssl_optional=1
{{- if $tls.verifyServerCert -}}
;mysql_ssl_ca_file={{ include "centreon-central.dbTlsCaPath" . }};mysql_ssl_verify_server_cert=1
{{- end -}}
{{- end -}}
{{- end -}}

{{/* Web feature flags with Vault on (PHP containers). */}}
{{- define "centreon-central.vaultEnv" -}}
{{- if .Values.vault.enabled }}
- { name: FILE_FEATURE_FLAGS, value: /var/lib/centreon/vault/features.json }
{{- end }}
{{- end -}}

{{/* PHP sessions in Valkey (in-chart or external) instead of files on the
     shared volume. */}}
{{- define "centreon-central.valkeyOn" -}}
{{- if or .Values.valkey.enabled .Values.valkey.external.host }}true{{ end -}}
{{- end -}}

{{- define "centreon-central.valkeyHostPort" -}}
{{- if .Values.valkey.enabled -}}
valkey:6379
{{- else -}}
{{ .Values.valkey.external.host }}:{{ .Values.valkey.external.port }}
{{- end -}}
{{- end -}}

{{/* VALKEY_PASSWORD as env var ENV: the external Secret when given, else the
     chart's Secret (secrets.valkeyPassword or secrets.existingSecret).
     (dict "ctx" $ "name" "ENV") */}}
{{- define "centreon-central.valkeyPasswordEnv" -}}
{{- $ext := .ctx.Values.valkey.external -}}
{{- if and (not .ctx.Values.valkey.enabled) $ext.existingSecret -}}
- name: {{ .name }}
  valueFrom:
    secretKeyRef:
      name: {{ $ext.existingSecret }}
      key: {{ $ext.existingSecretKey }}
{{- else -}}
{{ include "centreon-central.secretEnv" (dict "ctx" .ctx "name" .name "key" "VALKEY_PASSWORD") }}
{{- end -}}
{{- end -}}

{{/* fpm's session handler (image contract: the entrypoint URL-encodes the
     password into the save path). */}}
{{/*
Configuration export timeouts (MON-214473), all derived from
configurationExport.generationTimeoutSeconds: fpm runs the generation within
it and PHP must not stop it first; httpd must outlast the whole REST
generate-and-reload (generation + two gorgone deliveries of up to 60 s each).
*/}}
{{- define "centreon-central.generationTimeout" -}}
{{- int .Values.configurationExport.generationTimeoutSeconds -}}
{{- end }}

{{- define "centreon-central.exportTimeoutEnv" -}}
{{- $generation := include "centreon-central.generationTimeout" . | int -}}
- { name: CENTREON_CONFIGURATION_GENERATION_TIMEOUT, value: {{ $generation | quote }} }
- { name: PHP_MAX_EXECUTION_TIME, value: {{ max 300 (add $generation 60) | quote }} }
{{- end }}

{{- define "centreon-central.httpdProxyTimeout" -}}
{{- max 300 (add (include "centreon-central.generationTimeout" . | int) 240) -}}
{{- end }}

{{- define "centreon-central.fpmSessionEnv" -}}
{{- if include "centreon-central.valkeyOn" . }}
- { name: PHP_SESSION_HANDLER, value: redis }
- { name: PHP_SESSION_SAVE_PATH, value: {{ printf "tcp://%s" (include "centreon-central.valkeyHostPort" .) | quote }} }
{{ include "centreon-central.valkeyPasswordEnv" (dict "ctx" . "name" "PHP_SESSION_REDIS_PASSWORD") }}
{{- end }}
{{- end -}}
