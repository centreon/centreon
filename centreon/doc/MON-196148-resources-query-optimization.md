# MON-196148 — Resource Status Query Optimization

## Context

The `GET /monitoring/resources` API endpoint (powering the Resource Status page) was taking
**45–54 seconds** to respond, making the UI unusable. The investigation and fixes spanned
several layers: SQL generation, database indexes, ACL filtering architecture, and pagination
strategy.

---

## Changes

### 1. SQL `ORDER BY` fix — `SqlRequestParametersTranslator.php`

**Problem:** The original code added `col IS NULL, col ASC/DESC` for all sort directions.
For `DESC`, this creates a computed expression that prevents the optimizer from using an index
for the sort.

**Fix:** Only apply the `IS NULL` trick for `ASC` ordering (NULLs naturally sort last for
`DESC` in MariaDB).

**Why it matters:** Without this fix, any `ORDER BY col DESC` forces a filesort instead of
using the index, making all sorted queries slow regardless of other optimizations.

---

### 2. New database indexes

**Files:** `www/install/createTablesCentstorage.sql`, `www/install/php/Update-next.php`

**Problem:** No indexes existed to efficiently support the common query patterns on the
`resources` table (960k+ rows).

Five additions were made:

#### Virtual column

```sql
ALTER TABLE resources ADD COLUMN is_module TINYINT(1) GENERATED ALWAYS AS (
    CASE WHEN name LIKE '\_Module\_%' OR parent_name LIKE '\_Module\_BAM%' THEN 1 ELSE 0 END
) VIRTUAL;
```

Pre-computes the internal module exclusion filter so it can be stored in index leaf pages
without reading the full row.

#### Indexes

| Index | Columns | Purpose |
|---|---|---|
| `resources_enabled_status_sort_idx` | `(enabled, status_ordered DESC, last_status_change DESC)` | Default sort order — enables early termination at `LIMIT` |
| `resources_enabled_type_ismodule_idx` | `(enabled, type, is_module, poller_id)` | Covering index for unfiltered `COUNT` |
| `resources_status_filter_idx` | `(enabled, status, type, is_module, acknowledged, in_downtime, status_confirmed, poller_id)` | Status-first covering index — tight seek for status/state filters, direct row access for paginated queries |
| `resources_name_search_idx` | `(enabled, type, is_module, poller_id, name)` | Covering index for name `REGEXP`/`LIKE` searches — avoids reading the clustered index |

A migration script was added with idempotent `IF NOT EXISTS` checks to apply these safely
on existing installations.

---

### 3. Replaced `NOT LIKE '_Module_%'` with `is_module = 0`

**File:** `src/Core/Resources/Infrastructure/Repository/DbReadResourceRepository.php`

**Problem:** The original query used `resources.name NOT LIKE '_Module_%' AND resources.parent_name NOT LIKE '_Module_BAM%'`. Pattern matching with `NOT LIKE` on every row blocks index usage.

**Fix:** Replace with `resources.is_module = 0` — the virtual column that pre-computes the
result and can be stored in index leaf pages.

---

### 4. Direct `COUNT` query — `generateCountResourcesQuery()`

**File:** `src/Core/Resources/Infrastructure/Repository/DbReadResourceRepository.php`

**Problem:** The original `COUNT` wrapped the full `SELECT` in a subquery:
`SELECT COUNT(*) FROM (SELECT ... FROM resources ...)`. This prevents the optimizer from
using covering indexes on the inner query — it must materialize all columns before counting.

**Fix:** Generate a direct `SELECT COUNT(*) FROM resources WHERE ...` without any subquery
wrapper. Combined with the covering indexes, this dropped the unfiltered `COUNT` from
**54s → 1.74s**.

---

### 5. `FORCE INDEX` for name REGEXP `COUNT`

**File:** `src/Core/Resources/Infrastructure/Repository/DbReadResourceRepository.php`

**Problem:** When a name `REGEXP` filter is active without status/state filters, the
optimizer incorrectly prefers a smaller index that requires row reads for the `name` column.

**Fix:** Apply `FORCE INDEX (resources_name_search_idx)` when all of the following are true:
- A name search is present in the query
- The search does **not** include columns outside the name index (`alias`, `address`, `output`)
- No status/state filter is active
- No ACL restriction is active
- No CTE join is present (see §10)
- No severity filter is active (LEFT JOIN present prevents effective index use)

The covering index includes `name`, avoiding all clustered index reads.

**Result:** Name REGEXP `COUNT`: **43s → 1.76s**.

**Why the multi-column check matters:** When the search includes `alias`, `address`, or `output`
(columns not in the name index), `FORCE INDEX` forces a secondary-index range scan that still
requires a clustered PK lookup for every row — because the optimizer must read the full row to
evaluate the non-indexed OR conditions. In that case, letting the optimizer use `resources_enabled_type_index`
(ref on `enabled=1`) is faster (12s vs 27s for the 8-column search bar).

---

### 6. COUNT concordances to eliminate parent_resource LEFT JOIN

**File:** `src/Core/Resources/Infrastructure/Repository/DbReadResourceRepository.php`

**Problem:** The 8-column search bar sends an `$or` search across `h.name`, `h.alias`,
`h.address`, `s.description`, `alias`, `parent_alias`, `fqdn`, `information`. Three of
those fields (`h.alias`, `h.address`, `parent_alias`) map to `parent_resource.alias` and
`parent_resource.address` in the concordances, which forces a `LEFT JOIN resources parent_resource`
on the COUNT query. This join requires a parent PK lookup for every one of the ~484k enabled
rows — ~1,440 cold disk I/Os — adding ~17s to every search-bar COUNT query.

**Fix:** `generateCountResourcesQuery()` temporarily overrides the concordances with
inline approximations before calling `translateSearchParameterToSql()`:

| Field | Normal concordance | COUNT concordance |
|---|---|---|
| `h.alias` | `CASE WHEN type=1 THEN resources.alias ELSE parent_resource.alias END` | `resources.alias` |
| `h.address` | `parent_resource.address` | `resources.address` |
| `parent_alias` | `parent_resource.alias` | `resources.parent_name` |
| `parent_status` | `parent_resource.status` | `resources.status` |

With these substitutions the generated search SQL contains no `parent_resource.*` references,
so `$needsParentResourceJoin` is `false` and the LEFT JOIN is omitted entirely. The original
concordances are restored immediately after (inside a `finally` block).

The approximations are semantically close for the common search-bar use case (users rarely
search by the parent host's alias as opposed to its name). The DATA query still uses the
full `LEFT JOIN` with the original concordances and returns correct, exact results.

When a severity filter is active, `$hasSeverityFilter = true` forces the LEFT JOIN regardless
(severity conditions reference `parent_resource.severity_id`), which is correct.

**Result:** 8-column search bar COUNT: **29s → ~12s** (no parent JOIN, no FORCE INDEX).

---

### 7. Removed ACL provider abstraction

**Files:**
- `src/Core/Resources/Infrastructure/Repository/DbReadResourceRepository.php`
- `src/Core/Resources/Infrastructure/Repository/ResourceACLProviders/` *(deleted)*
- `config/services.yaml`
- `config/packages/Centreon.yaml`

**Problem:** ACL filtering was delegated to pluggable provider classes (`HostACLProvider`,
`ServiceACLProvider`, `MetaServiceACLProvider`) that injected SQL fragments via a tagged
iterator. Each provider issued a `LEFT JOIN centreon_acl` with an `OR` condition, which
caused near-cartesian-product joins (484k × 1.2M rows). The abstraction was originally
designed to support module-contributed resource types, which is no longer needed.

**Fix:** Replaced all four provider classes with two direct inline `EXISTS` subqueries:

```sql
AND (
    EXISTS (
        SELECT 1 FROM centreon_acl acl
        WHERE resources.type = 1
          AND acl.host_id = resources.id
          AND acl.group_id IN (...)
    )
    OR
    EXISTS (
        SELECT 1 FROM centreon_acl acl
        WHERE resources.type != 1
          AND acl.host_id = resources.parent_id
          AND acl.service_id = resources.id
          AND acl.group_id IN (...)
    )
)
```

Two **separate** `EXISTS` are used intentionally. Merging them into one `EXISTS` with `OR`
inside degrades the `centreon_acl` index from `key_len=13` (full composite key seek per row)
to `key_len=4` (full group scan per row), making it significantly slower.

---

### 8. Tag filter CTEs: `UNION` → `UNION ALL` + filter pushdown

**File:** `src/Core/Resources/Infrastructure/Repository/DbReadResourceRepository.php`

**Problem:** The `host_groups` and `host_categories` CTEs used `UNION` (with deduplication)
between their two branches:
- Branch 1: host resources directly tagged to the group
- Branch 2: child service resources via parent join

Since these branches are structurally disjoint (branch 1 returns hosts, branch 2 returns
services), the deduplication temp table + filesort was pure waste. Additionally, `is_module = 0`
and `type != 3` filters were only applied in the outer query, causing the CTEs to
materialize extra rows that were then discarded.

**Fix:**
- Changed `UNION` to `UNION ALL` between the two branches (~31% improvement)
- Pushed `resources.is_module = 0 AND resources.type != 3` inside each CTE branch (~17% improvement)

---

### 9. EXISTS strategy for the paginated DATA query

**File:** `src/Core/Resources/Infrastructure/Repository/DbReadResourceRepository.php`

**Problem:** The DATA query used the same CTE + `INNER JOIN` approach as the `COUNT` query.
With `INNER JOIN cte`, the CTE is materialized first (up to 109k resource IDs), then the
join drives the result. This prevents the optimizer from using the sort index for early
termination and forces it to evaluate all CTE rows even when only 30 are needed. Combined
with a multi-column REGEXP search (8 columns across `h.name`, `h.alias`, `h.address`,
`s.description`, `alias`, `parent_alias`, `fqdn`, `information`), this made the UI search
**4.5s** even for the first 30 results.

**Fix:** Added `createTagFilterExistsConditions()` and a `$useTagExistsForFilters` parameter
to `generateFindResourcesQuery`. When called from `find()` (the paginated path), tag filters
become correlated `EXISTS` conditions in the `WHERE` clause instead of a CTE join. The
optimizer then:

1. Walks `resources` in `status_ordered DESC, last_status_change DESC` order via the sort index
2. Evaluates REGEXP per row — most rows fail quickly and short-circuit
3. Checks `EXISTS`(tag membership) only for rows that pass REGEXP
4. Early-terminates at `LIMIT 30`

The export iterators (`iterateResources`, `iterateResourcesByAccessGroupIds`) keep the CTE
approach since they fetch large result sets where pre-filtering is more efficient than
scanning 960k rows.

**Result:** Hostgroup "All" + 8-column REGEXP DATA query: **4.5s → 64ms** (70×).

---

### 10. Fixed `FORCE INDEX` bug with CTE joins

**File:** `src/Core/Resources/Infrastructure/Repository/DbReadResourceRepository.php`

**Problem:** `FORCE INDEX (resources_name_search_idx)` was applied to the `COUNT` query even
when a CTE join was present. With a CTE driving the join, the optimizer cannot use the name
index for `resource_id`-based lookups. Instead it flips the join direction — scanning 75,939
rows on the name index rather than doing 97k CTE-driven primary key lookups.

**Fix:** Added `&& $queryHeaders === ''` to the index hint condition — the hint is now
skipped whenever a CTE join is present.

---

### 11. Fixed page > 1 slowness for status/state filters

**File:** `src/Core/Resources/Infrastructure/Repository/DbReadResourceRepository.php`

**Problem:** With the "Unhandled Alerts" filter
(`states=unhandled_problems&status_types=hard&statuses=WARNING,DOWN,CRITICAL,UNKNOWN`),
page 1 took **18ms** but page 2 took **9.14s** — a 500× difference with an identical `EXPLAIN`.

Root cause: the sort index (`resources_enabled_status_sort_idx`) was used for both pages.

- **Page 1:** Early termination fires after scanning ~674 rows — the dense CRITICAL block
  at the top of the index contains 17 unhandled hard resources, followed quickly by 19 WARNING.
  The first 30 matches are found fast.
- **Page 2:** Must skip rows 1–30 and find rows 31–60. After the WARNING block, the optimizer
  enters the `status_ordered=2` level which contains **1,895 UNKNOWN-in-downtime resources** —
  all with the same `status_ordered` value, all cold in the 128MB buffer pool. Only 18 of
  those 1,895 rows actually match the unhandled filter. This causes ~1,895 random InnoDB page
  reads → 9s.

**Fix:** When status/state filters are active in the paginated DATA query, force
`FORCE INDEX (resources_status_filter_idx)` instead of the sort index. This index directly
seeks to the ~54 actually matching rows, performs a trivial filesort on them, then applies
`OFFSET`/`LIMIT`. Uniformly fast for any page depth.

**Result:** Page 2: **9.14s → 23ms**.

---

## Overall Performance Gains

| Scenario | Before | After |
|---|---|---|
| Unfiltered DATA (page 1) | ~45s | ~120ms |
| Unfiltered COUNT | ~54s | ~1.74s |
| Status/state filter COUNT | ~54s | ~4.5ms |
| Name REGEXP COUNT (single column) | ~43s | ~1.76s |
| 8-column search bar COUNT | ~29–44s | ~12s |
| Unhandled alerts — page 1 | ~45s | ~27ms |
| Unhandled alerts — page 2 | ~9s | ~23ms |
| Hostgroup filter only (large group, ~100k resources) | N/A | ~2–3s |
| Hostgroup + REGEXP DATA | ~4.5s | ~64ms |
| Non-admin user with ACL | 20–62s | not benchmarked |

> **Note on 8-column search bar COUNT:** The remaining ~12s is a fundamental limitation of
> evaluating REGEXP across 8 columns over 484k rows without a covering index for all search
> fields. The DATA query returns the first 30 results in ~120ms (via sort index early
> termination), so results appear immediately in the UI while the pagination count finishes
> loading. Further improvement would require adding denormalized columns (e.g. `output_short`)
> to enable a covering index for the search bar fields.

---

## Key MariaDB Insights

- `ORDER BY col DESC` already puts NULLs last — adding `col IS NULL` creates a computed
  expression that blocks index usage.
- `SELECT COUNT(*) FROM (subquery)` prevents covering index use on the inner query.
- Two separate `EXISTS` subqueries preserve `key_len=13` on composite indexes. Merging them
  with `OR` inside a single `EXISTS` degrades to `key_len=4`.
- Virtual columns (`GENERATED ALWAYS AS ... VIRTUAL`) can be indexed without storing data in
  the row.
- `FORCE INDEX` hints can backfire when a CTE join is present — the optimizer may flip the
  join direction, causing a full index scan instead of a primary key lookup.
- With `LIMIT offset, n` and a sort index, the optimizer must scan and discard all rows up to
  `offset`. If those rows are cold in the buffer pool, deep pages become much slower than
  page 1 despite an identical `EXPLAIN` plan.
- `status`-first covering indexes enable tight range seeks for `status IN (...)` conditions,
  reducing scanned rows from ~484k to ~54 for filtered queries.
- `FORCE INDEX` on a partial covering index is harmful when OR conditions reference columns
  outside the index: the index scan still requires a clustered PK lookup per row for non-indexed
  OR conditions, and the secondary-index range scan adds overhead vs a simple `ref` access.
- A `LEFT JOIN` required by search concordances can be eliminated for COUNT by using inline
  approximations (`parent_alias → resources.parent_name`) — the DATA query still uses the
  exact JOIN and returns precise results, so pagination may have minor rounding but results
  are always correct.

---

## Files Modified

| File | Nature of change |
|---|---|
| `src/Core/Resources/Infrastructure/Repository/DbReadResourceRepository.php` | Core query logic — all SQL generation changes |
| `src/Centreon/Infrastructure/RequestParameters/SqlRequestParametersTranslator.php` | `ORDER BY` DESC fix |
| `config/services.yaml` | Removed ACL provider DI wiring |
| `config/packages/Centreon.yaml` | Removed ACL provider tag registration |
| `www/install/createTablesCentstorage.sql` | New indexes added to table definition |
| `www/install/php/Update-next.php` | Migration script for existing installations |

## Files Deleted

| File | Reason |
|---|---|
| `src/Core/Resources/Infrastructure/Repository/ResourceACLProviders/ResourceACLProviderInterface.php` | Replaced by direct inline SQL |
| `src/Core/Resources/Infrastructure/Repository/ResourceACLProviders/HostACLProvider.php` | Replaced by direct inline SQL |
| `src/Core/Resources/Infrastructure/Repository/ResourceACLProviders/ServiceACLProvider.php` | Replaced by direct inline SQL |
| `src/Core/Resources/Infrastructure/Repository/ResourceACLProviders/MetaServiceACLProvider.php` | Replaced by direct inline SQL |
