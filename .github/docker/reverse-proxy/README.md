> **Languages / Langues** — [English](#-english) · [Français](#-français)

<a name="-english"></a>

## 🇬🇧 English

# Reverse-proxy test stack (TLS termination)

Runs the Centreon `web` container behind a TLS-terminating reverse proxy
(external HTTPS, internal HTTP). A reusable building block to test connection
scenarios the standard e2e/API stack cannot cover: `X-Forwarded-Proto` handling,
auth/cookies behind a proxy, redirections.

```
browser --HTTPS--> web-proxy (nginx, TLS) --HTTP + X-Forwarded-Proto: https--> web (apache :80)
```

`apache-forwarded-proto.conf` makes apache trust `X-Forwarded-Proto`, so php-fpm
sees `REQUEST_SCHEME=https` while the real connection stays HTTP.

## Usage

```sh
cd .github/docker

# 1. one-time: self-signed cert for the proxy (git-ignored)
reverse-proxy/create-certs.sh

# 2. start (web image defaults to centreon-web-alma9:develop, override with WEB_IMAGE=…)
docker compose -f docker-compose.yml -f docker-compose.proxy.yml --profile proxy up -d

# 3. verify the wiring (expects https via proxy, http direct)
reverse-proxy/verify.sh
```

Expected output of step 3:

```
» Through the proxy (HTTPS) — expect X-Effective-Scheme: https
X-Effective-Scheme: https

» Direct to web (HTTP) — expect X-Effective-Scheme: http
X-Effective-Scheme: http
```

Then use Centreon through either entry point:

- Through the proxy: <https://localhost:4443/centreon> — `admin` / `Centreon!2021`
- Direct to `web` (HTTP, for comparison): <http://localhost:4000/centreon>

Per-scenario config goes through env, e.g. `CENTREON_INTERNAL_API_BASE_URL=http://127.0.0.1:80`.

```sh
# stop when done
docker compose -f docker-compose.yml -f docker-compose.proxy.yml --profile proxy down -v
```

## Files

- `../docker-compose.proxy.yml` — overlay: `web` override + `nginx-proxy` (profile `proxy`)
- `nginx.conf` — proxy vhost (TLS 443 → `web:80`, sets `X-Forwarded-Proto: https`)
- `apache-forwarded-proto.conf` — apache trusts `X-Forwarded-Proto`
- `create-certs.sh` — generates the self-signed proxy certificate
- `apache-debug-scheme.conf` + `verify.sh` — diagnostic `X-Effective-Scheme` header and its bash check (helpers)

<br>

---

<a name="-français"></a>

## 🇫🇷 Français

# Stack de test reverse-proxy (terminaison TLS)

Fait tourner le conteneur `web` de Centreon derrière un reverse proxy qui termine
le TLS (HTTPS externe, HTTP interne). Un socle réutilisable pour tester des
scénarios de connexion que la stack e2e/API standard ne couvre pas : gestion de
`X-Forwarded-Proto`, authentification/cookies derrière un proxy, redirections.

```
navigateur --HTTPS--> web-proxy (nginx, TLS) --HTTP + X-Forwarded-Proto: https--> web (apache :80)
```

`apache-forwarded-proto.conf` fait confiance à `X-Forwarded-Proto` côté apache,
si bien que php-fpm voit `REQUEST_SCHEME=https` alors que la connexion réelle
reste en HTTP.

## Utilisation

```sh
cd .github/docker

# 1. une fois : certificat auto-signé pour le proxy (non commité)
reverse-proxy/create-certs.sh

# 2. démarrer (image web par défaut : centreon-web-alma9:develop, surcharge via WEB_IMAGE=…)
docker compose -f docker-compose.yml -f docker-compose.proxy.yml --profile proxy up -d

# 3. vérifier le câblage (attendu : https via le proxy, http en direct)
reverse-proxy/verify.sh
```

Sortie attendue de l'étape 3 :

```
» Through the proxy (HTTPS) — expect X-Effective-Scheme: https
X-Effective-Scheme: https

» Direct to web (HTTP) — expect X-Effective-Scheme: http
X-Effective-Scheme: http
```

Ensuite, exploiter Centreon par l'une ou l'autre porte d'entrée :

- Via le proxy : <https://localhost:4443/centreon> — `admin` / `Centreon!2021`
- En direct sur `web` (HTTP, pour comparer) : <http://localhost:4000/centreon>

La config par scénario passe par l'environnement, ex. `CENTREON_INTERNAL_API_BASE_URL=http://127.0.0.1:80`.

```sh
# arrêter une fois terminé
docker compose -f docker-compose.yml -f docker-compose.proxy.yml --profile proxy down -v
```

## Fichiers

- `../docker-compose.proxy.yml` — overlay : surcharge `web` + service `nginx-proxy` (profil `proxy`)
- `nginx.conf` — vhost du proxy (TLS 443 → `web:80`, pose `X-Forwarded-Proto: https`)
- `apache-forwarded-proto.conf` — apache fait confiance à `X-Forwarded-Proto`
- `create-certs.sh` — génère le certificat auto-signé du proxy
- `apache-debug-scheme.conf` + `verify.sh` — en-tête diagnostic `X-Effective-Scheme` et sa vérification bash (helpers)
