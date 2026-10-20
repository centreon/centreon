{{- define "centreon-poller.name" -}}
{{- .Chart.Name | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{/* Release-prefixed: several pollers can share a namespace. */}}
{{- define "centreon-poller.fullname" -}}
{{- .Values.fullnameOverride | default .Release.Name | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{- define "centreon-poller.labels" -}}
helm.sh/chart: {{ printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" }}
{{ include "centreon-poller.selectorLabels" . }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end -}}

{{- define "centreon-poller.selectorLabels" -}}
app.kubernetes.io/name: {{ include "centreon-poller.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end -}}

{{/* Call with (dict "ctx" $ "image" .Values.images.xxx); empty tag = appVersion. */}}
{{- define "centreon-poller.image" -}}
{{- printf "%s:%s" .image.repository (toString (.image.tag | default .ctx.Chart.AppVersion)) -}}
{{- end -}}

{{/* GORGONE_UID: 18-digit ids lose precision once parsed as a number
     (`--set poller.id=...` gives a float64), and the central then rejects the
     node ("please registernodes"). */}}
{{- define "centreon-poller.id" -}}
{{- $id := required "poller.id is required (Add Poller --uid)" .Values.poller.id -}}
{{- if not (or (kindIs "string" $id) (kindIs "int64" $id) (kindIs "int" $id)) -}}
{{- fail "poller.id must be a string: quote it in the values file or use --set-string poller.id=<uid>" -}}
{{- end -}}
{{- toString $id -}}
{{- end -}}

{{/* No default VMware connector image: it embeds the licensed VMware Perl
     SDK, so it is built by the user and pushed to a private registry. */}}
{{- define "centreon-poller.vmwareImage" -}}
{{- $msg := "vmware.image.repository and vmware.image.tag are required: build the connector image with the VMware SDK and push it to a private registry (see README)" -}}
{{- printf "%s:%s" (required $msg .Values.vmware.image.repository) (toString (required $msg .Values.vmware.image.tag)) -}}
{{- end -}}

{{/* Secret holding the CMA TLS identity: provided, or issued by cert-manager. */}}
{{- define "centreon-poller.otelTlsSecret" -}}
{{- if .Values.engine.otel.tls.existingSecret -}}
{{- .Values.engine.otel.tls.existingSecret -}}
{{- else if .Values.engine.otel.tls.certManager.enabled -}}
{{- printf "%s-otel-tls" (include "centreon-poller.fullname" .) -}}
{{- end -}}
{{- end -}}

{{- define "centreon-poller.secretName" -}}
{{- required "secrets.existingSecret is required (GORGONE_TOKEN, APP_SECRET, SALT)" .Values.secrets.existingSecret -}}
{{- end -}}

{{/* env var from the poller Secret: (dict "ctx" $ "name" "ENV" "key" "KEY") */}}
{{- define "centreon-poller.secretEnv" -}}
- name: {{ .name }}
  valueFrom:
    secretKeyRef:
      name: {{ include "centreon-poller.secretName" .ctx }}
      key: {{ .key }}
{{- end -}}

{{/* Seed a volume from the image's own path, like Docker's named-volume copy-up.
     chown/chmod are non-fatal: some RWX backends (EFS access points) refuse them. */}}
{{- define "centreon-poller.seedFn" -}}
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
