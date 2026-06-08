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

Pour chaque mode, deux vérifications :

1. **Côté client (Web)** : connexion en clair ou TLS actif (et version TLS négociée le cas échéant).
2. **Côté serveur (MariaDB)** : le serveur compte les connexions chiffrées (`Ssl_accepts`) et le total reçu (`Connections`). Règle de validation :
   - TLS activé → `0 < Ssl_accepts ≤ Connections`
   - TLS désactivé → `Ssl_accepts = 0`

Une session API admin a aussi été ouverte pour confirmer le bon fonctionnement applicatif global. Pour TC-6, l'export de configuration Broker a été inspecté côté fichiers JSON.

Détails techniques et commandes reproductibles : `.github/docker/tls/README.md`.

## Détails par TC

### TC-1 — Mode 0 baseline (sans TLS)

**Setup** : stack de base seule, sans le fichier d'activation TLS (`docker-compose.tls.yml`), sans `.env.local.php` modifié.

**Vérifications** :
- Connexion DB Web → en clair (TLS désactivé, comportement attendu)
- Preuve serveur MariaDB : `Ssl_accepts=0` (pour `Connections=7309`) → conforme à la règle (TLS désactivé)
- Login API admin → HTTP 200 + token
- Logs PHP-FPM / Apache → aucune erreur SSL/TLS/PDO
- Logs Engine / Gorgone / Broker → rien à signaler

**Verdict** : PASS. Non-régression confirmée — sans variables TLS, la PR n'introduit aucune erreur.

### TC-2 — Mode 1 (TLS sans vérification)

**Setup** : activation TLS (fichier `docker-compose.tls.yml`) + `.env.local.php` monté dans le conteneur + `DATABASE_SSL_ENABLED=1`, `DATABASE_VERIFY_SERVER_CERT=0`.

**Vérifications** :
- Variables TLS bien lues par l'application en mode "TLS sans vérification du certificat serveur"
- Connexion DB Web → TLS 1.3 actif
- Preuve serveur MariaDB : `Ssl_accepts=25` (pour `Connections=63`) → `0 < 25 ≤ 63`, conforme à la règle (TLS actif)
- Login API admin → HTTP 200 + token
- Logs : aucune erreur

**Verdict** : PASS. Le mode TLS sans vérification du certificat serveur est correctement activé.

### TC-3 — Mode 2 (TLS avec vérification du CA)

**Setup** : activation TLS + `DATABASE_VERIFY_SERVER_CERT=1` + `DATABASE_CA_PATH=/certs/ca.pem`.

**Vérifications** :
- Variables TLS bien lues par l'application en mode "TLS avec vérification du certificat serveur via le CA fourni"
- Connexion DB Web → TLS 1.3 actif
- Preuve serveur MariaDB : `Ssl_accepts=25` (pour `Connections=62`) → `0 < 25 ≤ 62`, conforme à la règle (TLS actif). La vérification du certificat contre le CA, propre au Mode 2, est démontrée en TC-4.
- Login API admin → HTTP 200 + token

**Verdict** : PASS.

### TC-4 — Sécurité : CA invalide doit échouer

**Setup** : activation TLS + `VERIFY=1` + `CA_PATH=/certs/nope.pem` (fichier inexistant).

**Vérifications** :
- Le container `web` ne devient jamais healthy, il échoue pendant l'install
- Erreur explicite dans les logs :
  ```
  PHP Fatal error: Uncaught Exception:
    SQLSTATE[HY000] [2002] Cannot connect to MySQL using SSL
    in /usr/share/centreon/www/class/centreonDB.class.php:166
  ```
- Aucune connexion en clair effectuée — pas de fallback silencieux

**Verdict** : PASS. Avec un CA invalide, la connexion échoue franchement et aucune connexion en clair n'est tentée en second essai : le comportement de sécurité attendu est bien respecté.

### TC-5 — Outils CLI sous TLS

**Setup** : Mode 2 valide.

**Objectif** : s'assurer que les outils en ligne de commande qui se connectent à la base passent eux aussi par le TLS (et pas seulement l'interface Web).

**Tests** :
- `update_centreon_storage_logs.php` : **pas pu être testé**. Cet outil n'est utilisé que lors d'une mise à jour de Centreon à partir d'une archive, et il n'est pas présent dans l'image de test. Il devra donc être validé sur un vrai environnement de mise à jour.
- `generateSqlLite` : testé, fonctionne normalement.

**Verdict** : PASS, avec une réserve : il reste à vérifier `update_centreon_storage_logs.php` lors d'une vraie mise à jour. À confirmer par le dev/QA.

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

**Setup** : stack redémarrée sans le fichier d'activation TLS, sans `.env.local.php` modifié.

**Vérifications** :
- Variables TLS absentes (état initial restauré)
- Connexion DB Web → en clair (TLS désactivé)
- Preuve serveur MariaDB : `Ssl_accepts=0` après reprise en clair → conforme à la règle (TLS désactivé), aucune connexion TLS résiduelle
- Login API admin → HTTP 200 + token
- Broker JSON : aucun champ `db_ssl_*` (les champs disparaissent quand TLS désactivé)

**Verdict** : PASS. La désactivation est propre, aucun reste de configuration TLS résiduelle.

### TC-8 — Vérifier que les scripts supprimés ne sont plus appelés

**Objectif** : la PR supprime deux anciens scripts (`export-mysql-indexes` et `import-mysql-indexes`). Ce test s'assure que leur suppression est propre — qu'aucun code, fichier de configuration ou tâche planifiée (cron) ne tente encore de les exécuter, ce qui provoquerait une erreur.

**Vérifications** :
- Les deux scripts ne sont effectivement plus présents.
- Aucune référence à ces scripts ne subsiste dans les fichiers de Centreon.
- Aucune tâche planifiée (cron) ne les appelle.

**Verdict** : PASS. Suppression propre, aucune référence orpheline.

## Out of scope (à tester ailleurs)

- Scripts Perl (`centreon-backup.pl`, `changeRrdDsName.pl`, `logsMigration.pl`) : non couverts par la PR, CTOR-2042 pas démarré
- Matrice OS / DB (alma9/alma10/trixie × MariaDB 10.11/11.8 / MySQL 8.0/8.4) : Phase 2 séparée
- gorgone : non couvert par la PR, MON-195933 séparé
- `update_centreon_storage_logs.php` : à tester sur env d'upgrade tarball
- Vrai test UI (Cypress) : la QA actuelle est API-driven
