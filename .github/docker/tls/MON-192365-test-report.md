**Ticket** : [MON-192365 — [Web] Secured TLS connection](https://centreon.atlassian.net/browse/MON-192365)

**Tester image** : `docker.centreon.com/centreon/centreon-web-alma9:MON-192365`

**Test stack** : `.github/docker/docker-compose.yml` + `.github/docker/tls/docker-compose.tls.yml`

**Tester** : Jérémy Delpierre

**Date** : Juin 2025

## Résumé des cas de tests exécutés

| TC | Résumé | Statut |
|---|---|---|
| TC-1 | Mode 0 baseline (sans TLS) — non-régression | PASS |
| TC-2 | Mode 1 TLS sans vérification (astuce PDO `SSL_CA=''`) | PASS |
| TC-3 | Mode 2 TLS avec vérification du CA | PASS |
| TC-4 | Mode 2 avec CA invalide → doit échouer (sécurité) | PASS |
| TC-5 | Outils CLI sous TLS | PASS (avec remarque, voir notes) |
| TC-6 | Export de config Broker propage les champs `db_ssl_*` | PASS |
| TC-7 | Bascule TLS → clair (retour à l'état initial) | PASS |
| TC-8 | Scripts supprimés non référencés | PASS |

**Verdict global** : **PASS**

la PR est fonctionnellement correcte et **mergeable**, sous réserve de la note  ci-dessous concernant la propagation des variables.

## important — à clarifier avec DEV et SRE (surtout pour le Cloud)

**Conséquence opérationnelle** :
- OnPrem : OK si l'admin crée `.env.local` comme prévu dans la spec
- Cloud : **à vérifier comment compliance injecte les vars**
- Docker/K8s CI : il faut générer un `.env.local` au boot du container, sinon la feature ne s'active jamais

**Questions ouvertes à poser avant merge** :
1. Qui va se charger de générer les vars en Cloud (compliance, deployment, code base) ?
2. Pour les futur utilisateurs OnPrem, faut-il documenter explicitement la contrainte "fichier `.env.local` obligatoire" coté Documentation lié à ce qui est prévu dans MON-193496 ?

## Test environment

Stack docker minimale validée :
- centreon-web-alma9:MON-192365
- bitnamilegacy/mariadb:10.11
- Création d'un docker-compose TLS spécifique : `.github/docker/tls/docker-compose.tls.yml`
- Certificats self-signed (SAN — *Subject Alternative Name*, noms d'hôte autorisés pour le cert : db, db-remote, localhost, 127.0.0.1) générés par un fichier `.github/docker/tls/gen-certs.sh`
- Configuration applicative TLS injectée via le fichier `.github/docker/tls/env.local.php`, monté dans le container à l'emplacement `.env.local.php` (simulation de la conf qu'un admin OnPrem créerait à la main)

Matrice OS / DB
- /!\ non-couverte par ce rapport — sera l'objet d'une Phase 2 séparée une fois la PR spécifique mergée

## Méthode de vérification

Pour chaque mode, deux angles d'observation ont été utilisés :

1. **Côté client (Web)** : connexion en clair ou TLS actif (et version TLS négociée le cas échéant).
2. **Côté serveur (MariaDB)** : compteur `Ssl_accepts` (handshakes TLS comptabilisés) confronté au total `Connections`. Si TLS est inopérant côté client, `Ssl_accepts` reste à 0 ; sinon il s'incrémente à chaque connexion TLS établie.

Une session API admin a aussi été ouverte pour confirmer le bon fonctionnement applicatif global. Pour TC-6, l'export de configuration Broker a été inspecté côté fichiers JSON.

Détails techniques et commandes reproductibles : `.github/docker/tls/README.md`.

## Détails par TC

### TC-1 — Mode 0 baseline (sans TLS)

**Setup** : stack standard, aucun override TLS, aucun `.env.local.php` modifié.

**Vérifications** :
- Connexion DB Web → en clair (TLS désactivé, comportement attendu)
- Preuve serveur MariaDB : `Ssl_accepts=0` pour `Connections=7309` — aucune connexion TLS établie
- Login API admin → HTTP 200 + token
- Logs PHP-FPM / Apache → aucune erreur SSL/TLS/PDO
- Logs Engine / Gorgone / Broker → rien à signaler

**Verdict** : PASS. Non-régression confirmée — sans variables TLS, la PR n'introduit aucune erreur.

### TC-2 — Mode 1 (TLS sans vérification)

**Setup** : override TLS + bind-mount `.env.local.php` + `DATABASE_SSL_ENABLED=1`, `DATABASE_VERIFY_SERVER_CERT=0`.

**Vérifications** :
- Variables TLS bien lues par l'application en mode "TLS sans vérification du certificat serveur"
- Connexion DB Web → TLS 1.3 actif
- Preuve serveur MariaDB : `Ssl_accepts=25` sur `Connections=63` — les connexions du Web sont bien en TLS (les ~38 restantes sont des connexions internes au container : healthcheck bash TCP probe, client mariadb local en socket Unix, etc.)
- Login API admin → HTTP 200 + token
- Logs : aucune erreur

**Verdict** : PASS. Le mode TLS sans vérification du certificat serveur est correctement activé — cas typique des déploiements en certificats self-signed.

### TC-3 — Mode 2 (TLS avec vérification du CA)

**Setup** : override TLS + `DATABASE_VERIFY_SERVER_CERT=1` + `DATABASE_CA_PATH=/certs/ca.pem`.

**Vérifications** :
- Variables TLS bien lues par l'application en mode "TLS avec vérification du certificat serveur via le CA fourni"
- Connexion DB Web → TLS 1.3 actif
- Preuve serveur MariaDB : `Ssl_accepts=25` sur `Connections=62` — comportement identique au Mode 1, le client refuserait ici la connexion si le CA était invalide (cf. TC-4)
- Login API admin → HTTP 200 + token

**Verdict** : PASS.

### TC-4 — Sécurité : CA invalide doit échouer

**Setup** : override TLS + `VERIFY=1` + `CA_PATH=/certs/nope.pem` (fichier inexistant).

**Vérifications** :
- Le container `web` ne devient jamais healthy, il échoue pendant l'install
- Erreur explicite dans les logs :
  ```
  PHP Fatal error: Uncaught Exception:
    SQLSTATE[HY000] [2002] Cannot connect to MySQL using SSL
    in /usr/share/centreon/www/class/centreonDB.class.php:166
  ```
- Aucune connexion en clair effectuée — pas de fallback silencieux

**Verdict** : PASS — la sécurité est tenue. Petite note d'UX : le container essaie plusieurs étapes d'install qui échouent en cascade avant de s'arrêter ; une vérif de connexion DB plus tôt améliorerait le feedback admin, mais pas un blocker.

### TC-5 — Outils CLI sous TLS

**Setup** : Mode 2 valide.

**Tests** :
- `tools/update_centreon_storage_logs.php` : **non testé** — non packagé dans l'image dev (`/usr/share/centreon/tools/` n'existe pas). C'est un script d'upgrade depuis tarball, à valider sur un environnement install/upgrade réel.
- `bin/generateSqlLite -p 1` : OK, sortie attendue `Poller (id:0): Sqlite database successfully created`

**Verdict** : PASS (avec remarque). À compléter par le dev/QA sur un setup d'upgrade pour valider `update_centreon_storage_logs.php` en conditions réelles.

### TC-6 — Export de configuration Broker

**Setup** : Mode 2 valide. Broker config généré au démarrage du container.

**Vérifications** :
```
/etc/centreon-broker/central-broker.json:
                "db_ssl_enabled": true,
                "db_ssl_verify_cert": true,
                "db_ssl_ca": "/certs/ca.pem"
```
Champs présents et valeurs cohérentes. Absents des `central-module.json` / `central-rrd.json` (logique : pas d'output SQL).

**Verdict** : PASS. La chaîne `.env.local.php` → PHP → broker config-gen → JSON broker fonctionne.

### TC-7 — Bascule TLS → clair (retour à l'état initial)

**Setup** : stack remontée sans aucun override TLS, sans `.env.local.php` patché.

**Vérifications** :
- Variables TLS absentes (état initial restauré)
- Connexion DB Web → en clair (TLS désactivé)
- Preuve serveur MariaDB : `Ssl_accepts=0` après reprise en clair — aucune connexion TLS résiduelle
- Login API admin → HTTP 200 + token
- Broker JSON : aucun champ `db_ssl_*` (les champs disparaissent quand TLS désactivé)

**Verdict** : PASS. La désactivation est propre, aucun reste de configuration TLS résiduelle.

### TC-8 — Scripts supprimés non référencés

**Vérifications** :
- `/usr/share/centreon/bin` ne contient plus `export-mysql-indexes` ni `import-mysql-indexes`
- Aucune référence à ces scripts dans `/usr/share/centreon` et `/etc/centreon`
- Aucun cron qui les appelle

**Verdict** : PASS. Cleanup safe.

## Notes pour le rapport global TLS (Confluence pageId 3573547624)

À rajouter dans la page Confluence "QA - Audit for TLS campaign" lors d'une prochaine itération :

1. **Confirmation que la variable canonique est `DATABASE_CA_PATH`** (et non `DATABASE_CA_CERT_PATH`). Confirmé par lecture du code `DatabaseTLSResolver` ligne 63.
2. **Le code TLS est désactivé en environnement `test`** : commit `disable TLS connection decorator in test environment` — les tests phpunit ne valident pas TLS, c'est forcément manuel.
3. **Le finding sur la propagation des variables d'environnement** (ci-dessus, section "important — à clarifier avec DEV et SRE"). Critique pour le déploiement Cloud.
4. **Modules legacy (DSM, open-tickets, AWIE)** : héritent automatiquement du TLS via `CentreonDB`, validé indirectement par TC-2/3 (mêmes classes wrappers).

## Out of scope (à tester ailleurs)

- Scripts Perl (`centreon-backup.pl`, `changeRrdDsName.pl`, `logsMigration.pl`) : non couverts par la PR, CTOR-2042 pas démarré
- Matrice OS / DB (alma9/alma10/trixie × MariaDB 10.11/11.8 / MySQL 8.0/8.4) : Phase 2 séparée
- gorgone : non couvert par la PR, MON-195933 séparé
- `update_centreon_storage_logs.php` : à tester sur env d'upgrade tarball
- Vrai test UI (Cypress) : la QA actuelle est API-driven

---

## Jira comment draft (English, for copy-paste on MON-192365)

```
QA report — manual test session, branch MON-192365, image centreon-web-alma9:MON-192365 + mariadb:10.11

8 test cases executed (Mode 0/1/2, security failure injection, CLI, broker export, reverse switch, removed scripts cleanup).

Result: PASS. Code is functionally correct. Mergeable subject to one clarification below.

⚠️ Finding to clarify before merge:
DatabaseTLSResolver reads $_ENV['DATABASE_SSL_ENABLED'], but PHP variables_order=GPCS in the Centreon image, so $_ENV is not populated from system env. Symfony Dotenv only populates $_ENV from .env/.env.local/.env.local.php. Consequence: Docker `environment:` or K8s env vars alone do NOT activate TLS. Tests had to bind-mount a custom .env.local.php matching the OnPrem admin spec.

Questions:
1. How are TLS vars propagated in Cloud? (compliance, deployment path)
2. Should the constraint "vars MUST live in .env*, not in shell env" be explicit in the doc subtask MON-193496?
3. Could DatabaseTLSResolver fall back to getenv() when $_ENV is empty, to cover both deployment models?

Other notes:
- TC-4 wrong-CA properly fails with explicit PDO error in centreonDB.class.php:166 — no silent fallback, security upheld
- Broker config-gen properly injects db_ssl_enabled / db_ssl_verify_cert / db_ssl_ca in central-broker.json (MON-195898 chain)
- tools/update_centreon_storage_logs.php not packaged in the dev image; to validate on an upgrade tarball
- Matrix OS/DB out of scope, will run after MON-197422 (PR #10385) provides the official CI infra

Full report: .github/docker/tls/MON-192365-test-report.md (local, not committed)
```
