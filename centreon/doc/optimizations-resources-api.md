# Optimisations `GET /monitoring/resources`

## Contexte

Point de départ : **45–54 secondes** sur `centreon_storage.resources` (960K lignes).
Cause racine : requêtes sans index utilisables, scans complets de table.

Branche : `MON-196148-RS-FTW`
Fichiers principaux modifiés :
- `src/Core/Resources/Infrastructure/Repository/DbReadResourceRepository.php`
- `src/Centreon/Infrastructure/RequestParameters/SqlRequestParametersTranslator.php`
- `www/install/createTablesCentstorage.sql`
- `www/install/php/Update-next.php`

---

## 1. Colonne virtuelle `is_module`

**Problème** : le filtre `NOT LIKE '_Module_%'` sur chaque ligne n'est pas indexable, forçant un accès au clustered index pour chaque row.

**Solution** : colonne générée `GENERATED ALWAYS AS ... VIRTUAL`, indexable sans stocker de données supplémentaires dans la ligne.

```sql
ALTER TABLE resources
ADD COLUMN is_module TINYINT(1) GENERATED ALWAYS AS (
    CASE WHEN name LIKE '\_Module\_%'
          OR parent_name LIKE '\_Module\_BAM%'
    THEN 1 ELSE 0 END
) VIRTUAL;
```

Toutes les requêtes utilisent désormais `resources.is_module = 0`.

---

## 2. Nouveaux index MariaDB

Cinq index ajoutés pour couvrir les patterns de requêtes identifiés :

| Index | Colonnes | Usage principal |
|-------|----------|-----------------|
| `resources_enabled_status_sort_idx` | `(enabled, status_ordered DESC, last_status_change DESC)` | `ORDER BY` sans filtre — parcours en ordre de tri, early termination à `LIMIT` |
| `resources_enabled_type_ismodule_idx` | `(enabled, type, is_module, poller_id)` | COUNT non filtré — index couvrant |
| `resources_status_filter_idx` | `(enabled, status, type, is_module, acknowledged, in_downtime, status_confirmed, poller_id)` | COUNT avec filtre status/state — seek direct sur les lignes matching |
| `resources_name_search_idx` | `(enabled, type, is_module, poller_id, name)` | COUNT avec recherche par nom — évite les row reads sur la PK |

```sql
ALTER TABLE resources ADD INDEX resources_enabled_status_sort_idx      (enabled, status_ordered DESC, last_status_change DESC);
ALTER TABLE resources ADD INDEX resources_enabled_type_ismodule_idx    (enabled, type, is_module, poller_id);
ALTER TABLE resources ADD INDEX resources_status_filter_idx            (enabled, status, type, is_module, acknowledged, in_downtime, status_confirmed, poller_id);
ALTER TABLE resources ADD INDEX resources_name_search_idx              (enabled, type, is_module, poller_id, name);
```

---

## 3. Fix `ORDER BY` dans `SqlRequestParametersTranslator`

**Problème** : le trick `col IS NULL, col ASC` (pour mettre les NULLs en dernier) était appliqué également pour `DESC`. Cela crée une expression calculée que MariaDB ne peut pas satisfaire avec un index, bloquant l'utilisation du sort index.

**Solution** : `col IS NULL` uniquement pour `ASC`. Pour `DESC`, MariaDB place les NULLs en dernier nativement — aucun trick nécessaire.

```
ASC  → col IS NULL, col ASC    ✓ (NULLs en dernier)
DESC → col DESC                ✓ (NULLs en dernier nativement)
```

---

## 4. COUNT direct sans subquery wrapper

**Problème** : `SELECT COUNT(*) FROM (SELECT ... FROM resources ...)` — le wrapper empêche l'optimiseur d'utiliser les index couvrants de la sous-requête (il doit lire toutes les colonnes pour le SELECT interne).

**Solution** : `generateCountResourcesQuery()` génère un `SELECT COUNT(*)` direct avec ses propres concordances adaptées, sans subquery.

---

## 5. COUNT sans LEFT JOIN `parent_resource`

**Problème** : le `LEFT JOIN` au parent (jusqu'à 960K × lookup PK) était systématique même pour les COUNT où seule la pagination a besoin du résultat.

**Solution** : concordances inline spécifiques au COUNT — approximations suffisantes pour la pagination :

| Concordance DATA query | Concordance COUNT query |
|------------------------|-------------------------|
| `parent_resource.alias` | `resources.alias` |
| `parent_resource.address` | `resources.address` |
| `parent_resource.alias` (parent_alias) | `resources.parent_name` |

Le JOIN reste dans la DATA query pour la précision des résultats. Le JOIN est rétabli dans le COUNT uniquement quand un filtre severity est actif (il référence `parent_resource.severity_id`).

---

## 6. `FORCE INDEX` adaptatif sur le COUNT

L'index forcé dépend des filtres actifs :

| Situation | Index forcé | Raison |
|-----------|-------------|--------|
| Recherche `name` seul | `resources_name_search_idx` | Index couvrant, évite les row reads |
| Recherche multi-colonnes (alias, address, output) | aucun | Le scan secondaire + PK lookup est **plus lent** que le ref scan sur `resources_enabled_type_index` |
| Filtre status/state actif | `resources_status_filter_idx` | Seek direct sur les lignes matching |
| CTE ou JOIN présent | aucun | Le CTE pilote le join, l'index de nom ne s'applique pas |

---

## 7. EXISTS → CTE `acl_accessible` (stratégie adaptative)

**Problème** : avec `EXISTS (SELECT 1 FROM centreon_acl WHERE ...)`, l'optimiseur scanne 884K lignes via le sort index en vérifiant chaque ligne contre l'ACL. Avec seulement 752 ressources accessibles sur 960K (0.08%), le filtre ne passe presque jamais → scan quasi complet → 20s.

**Diagnostic ANALYZE** :
```
| resources | resources_enabled_status_sort_idx | r_rows=884467 | r_filtered=0.01% |
```

**Solution** : stratégie adaptative basée sur le volume d'entrées ACL.

### Critère de sélection

```
COUNT(*) FROM centreon_acl WHERE group_id IN (...)
```

| Résultat | Stratégie | Raison |
|----------|-----------|--------|
| **< 20 000** | **CTE** `acl_accessible` | Matérialise les IDs accessibles, pilote le JOIN depuis ce petit ensemble. Évite le scan de 960K lignes. |
| **≥ 20 000** | **EXISTS** correlated | Sélectivité ACL élevée → le sort index early-termine après quelques lignes. Trier un grand CTE serait contreproductif. |

### Structure du CTE

```sql
WITH acl_accessible AS (
    -- Hôtes (type = 1)
    SELECT r.resource_id
    FROM centreon_acl acl
    INNER JOIN resources r
        ON r.type = 1 AND r.id = acl.host_id
        AND r.enabled = 1 AND r.is_module = 0
    WHERE acl.service_id IS NULL
      AND acl.group_id IN (...)

    UNION ALL  -- UNION ALL : hôtes et services sont disjoints par type

    -- Services / métaservices (type != 1)
    SELECT r.resource_id
    FROM centreon_acl acl
    INNER JOIN resources r
        ON r.id = acl.service_id AND r.parent_id = acl.host_id
        AND r.type != 3 AND r.enabled = 1 AND r.is_module = 0
    WHERE acl.service_id IS NOT NULL
      AND acl.group_id IN (...)
)
SELECT ... FROM resources
INNER JOIN acl_accessible ON resources.resource_id = acl_accessible.resource_id
...
```

### Cache du COUNT ACL

Le résultat du `COUNT(*)` sur `centreon_acl` est mis en cache dans `$aclCountCache` (clé = liste triée des group IDs). Ainsi, dans un même request lifecycle, `find()` et `count()` partagent le même résultat sans double requête.

---

## 8. Pre-join pour les filtres de tags dans la DATA query

Les filtres hostgroup / host category / servicegroup / service category utilisent des **tables dérivées jointes par INNER JOIN** (méthode `createTagFilterPreJoinClauses()`).

### Pourquoi pas EXISTS ?

L'approche `OR EXISTS` (direct + propagation parent) déclenchait systématiquement `Using temporary; Using filesort` sur la table `resources`, forçant un scan complet de 960K lignes même avec `FORCE INDEX` sur le sort index. Raison : MariaDB matérialise les deux branches de l'OR EXISTS séparément (deux semi-joins), ce qui empêche l'utilisation du sort index pour l'early termination.

**`EXISTS(UNION ALL)` testé et rejeté** : MariaDB exécute la UNION ALL comme sous-requête corrélée (`DEPENDENT SUBQUERY`), évaluée pour chaque ligne de l'outer query → **60s** pour 87 résultats.

### Solution : tables dérivées pré-matérialisées

Pour chaque type de tag actif, une table dérivée est construite et jointe :

```sql
INNER JOIN (
    -- ressources directement dans le hostgroup
    SELECT rt.resource_id
    FROM resources_tags rt INNER JOIN tags t ON t.tag_id = rt.tag_id
    WHERE t.type = 1 AND t.name IN (...)
    UNION
    -- services dont l'hôte parent est dans le hostgroup
    SELECT child.resource_id
    FROM resources child
    INNER JOIN resources parent ON parent.id = child.parent_id AND parent.type = 1 AND parent.enabled = 1
    INNER JOIN resources_tags rt ON rt.resource_id = parent.resource_id
    INNER JOIN tags t ON t.tag_id = rt.tag_id
    WHERE t.type = 1 AND t.name IN (...)
) hg_pj ON hg_pj.resource_id = resources.resource_id

INNER JOIN (
    SELECT rt.resource_id
    FROM resources_tags rt INNER JOIN tags t ON t.tag_id = rt.tag_id
    WHERE t.type = 2 AND t.name IN (...)
) sc_pj ON sc_pj.resource_id = resources.resource_id
```

MariaDB matérialise d'abord les petits ensembles de tags (~46 hôtes parents → ~500 services pour un hostgroup, ~14K pour une service category), calcule leur intersection (~87 ressources), puis fait des lookups par PK — au lieu de scanner 960K lignes.

### FORCE INDEX

- Filtre status/state actif → `FORCE INDEX (resources_status_filter_idx)`
- Filtres de tags seuls (pre-join) → **aucun hint** (l'optimiseur pilote depuis les tables dérivées via PK)
- ACL CTE actif → aucun hint (le CTE pilote)

---

## 9. UNION ALL dans les CTEs de tags

Remplacement de `UNION` par `UNION ALL` dans les branches hôtes/services des CTEs de tags. Les deux branches sont structurellement disjointes par `type` — la déduplication de `UNION` était inutile et coûteuse.

---

## Résultats

| Scénario | Avant | Après |
|----------|-------|-------|
| DATA query sans filtre (LIMIT 30) | 45s | 0.12s |
| COUNT non filtré | 54s | 1.94s |
| COUNT filtre status/state | 54s+ | 4.5ms |
| DATA query ACL actif (page 1) | 20s | <1s |
| Page 2 "unhandled alerts" | 9.14s | 23ms |
| DATA + hostgroup + recherche 8 colonnes | 4.5s | 64ms |
| DATA + hostgroup + service_category | 10.35s | 190ms |
| DATA + hostgroup seul (pre-join) | ~10s | 23ms |
| COUNT recherche 8 colonnes (REGEXP) | 29–44s | ~14s _(limite fondamentale du REGEXP)_ |

> **Note** : le COUNT sur une recherche REGEXP 8 colonnes reste à ~14s car aucun index couvrant ne peut inclure `alias`, `address` et `output` simultanément. La DATA query correspondante retourne en 120ms grâce à l'early termination du sort index.
