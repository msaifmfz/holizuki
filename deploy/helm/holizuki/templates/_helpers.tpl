{{- define "holizuki.name" -}}
{{- default .Chart.Name .Values.nameOverride | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{- define "holizuki.fullname" -}}
{{- if .Values.fullnameOverride -}}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" -}}
{{- else -}}
{{- printf "%s-%s" .Release.Name (include "holizuki.name" .) | trunc 63 | trimSuffix "-" -}}
{{- end -}}
{{- end -}}

{{- define "holizuki.labels" -}}
helm.sh/chart: {{ printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" }}
app.kubernetes.io/name: {{ include "holizuki.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
app.kubernetes.io/version: {{ .Values.release | quote }}
app.kubernetes.io/part-of: holizuki
holizuki.dev/environment: {{ .Values.environment | quote }}
holizuki.dev/php-version: {{ .Values.image.phpVersion | quote }}
{{- end -}}

{{- define "holizuki.selectorLabels" -}}
app.kubernetes.io/name: {{ include "holizuki.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end -}}

{{- define "holizuki.serviceAccountName" -}}
{{- if .Values.serviceAccount.create -}}
{{- default (include "holizuki.fullname" .) .Values.serviceAccount.name -}}
{{- else -}}
{{- default "default" .Values.serviceAccount.name -}}
{{- end -}}
{{- end -}}

{{- define "holizuki.image" -}}
{{- printf "%s@%s" .Values.image.repository .Values.image.digest -}}
{{- end -}}

{{- define "holizuki.uploadsClaim" -}}
{{- if .Values.uploads.existingClaim -}}
{{- .Values.uploads.existingClaim -}}
{{- else -}}
{{- printf "%s-uploads" (include "holizuki.fullname" .) -}}
{{- end -}}
{{- end -}}

{{- define "holizuki.podSecurityContext" -}}
runAsNonRoot: true
runAsUser: 10001
runAsGroup: 10001
fsGroup: 10001
fsGroupChangePolicy: OnRootMismatch
seccompProfile:
  type: RuntimeDefault
{{- end -}}

{{- define "holizuki.containerSecurityContext" -}}
allowPrivilegeEscalation: false
capabilities:
  drop:
    - ALL
privileged: false
readOnlyRootFilesystem: true
runAsNonRoot: true
runAsUser: 10001
runAsGroup: 10001
{{- end -}}

{{- define "holizuki.imagePullSecrets" -}}
{{- with .Values.image.pullSecrets }}
imagePullSecrets:
  {{- range . }}
  - name: {{ . }}
  {{- end }}
{{- end }}
{{- end -}}

{{- define "holizuki.env" -}}
envFrom:
  - configMapRef:
      name: {{ include "holizuki.fullname" . }}
  - secretRef:
      name: {{ .Values.runtimeSecretName }}
{{- end -}}

{{- define "holizuki.migrationEnv" -}}
envFrom:
  - secretRef:
      name: {{ .Values.runtimeSecretName }}
env:
  - name: APP_NAME
    value: {{ .Values.application.name | quote }}
  - name: APP_ENV
    value: {{ .Values.environment | quote }}
  - name: APP_DEBUG
    value: "false"
  - name: APP_URL
    value: {{ printf "https://%s" .Values.host | quote }}
  - name: APP_RELEASE
    value: {{ .Values.release | quote }}
  - name: CACHE_STORE
    value: {{ .Values.application.cacheStore | quote }}
  - name: DB_CONNECTION
    value: "pgsql"
  - name: DB_HOST
    value: {{ .Values.database.host | quote }}
  - name: DB_PORT
    value: {{ .Values.database.port | quote }}
  - name: DB_DATABASE
    value: {{ .Values.database.name | quote }}
  - name: DB_USERNAME
    value: {{ .Values.database.username | quote }}
  - name: DB_SSLMODE
    value: {{ .Values.database.sslMode | quote }}
  - name: LOG_CHANNEL
    value: "stderr"
  - name: TELESCOPE_ENABLED
    value: "false"
{{- end -}}

{{- define "holizuki.volumeMounts" -}}
volumeMounts:
  - name: bootstrap-cache
    mountPath: /app/bootstrap/cache
  - name: framework
    mountPath: /app/storage/framework
  - name: temporary
    mountPath: /tmp
  {{- if .Values.uploads.enabled }}
  - name: uploads
    mountPath: /app/storage/app
  {{- end }}
{{- end -}}

{{- define "holizuki.volumes" -}}
volumes:
  - name: bootstrap-cache
    emptyDir:
      sizeLimit: 128Mi
  - name: framework
    emptyDir:
      sizeLimit: 256Mi
  - name: temporary
    emptyDir:
      sizeLimit: 256Mi
  {{- if .Values.uploads.enabled }}
  - name: uploads
    persistentVolumeClaim:
      claimName: {{ include "holizuki.uploadsClaim" . }}
{{- end }}
{{- end -}}

{{- define "holizuki.ephemeralVolumeMounts" -}}
volumeMounts:
  - name: bootstrap-cache
    mountPath: /app/bootstrap/cache
  - name: framework
    mountPath: /app/storage/framework
  - name: temporary
    mountPath: /tmp
{{- end -}}

{{- define "holizuki.ephemeralVolumes" -}}
volumes:
  - name: bootstrap-cache
    emptyDir:
      sizeLimit: 128Mi
  - name: framework
    emptyDir:
      sizeLimit: 256Mi
  - name: temporary
    emptyDir:
      sizeLimit: 256Mi
{{- end -}}
