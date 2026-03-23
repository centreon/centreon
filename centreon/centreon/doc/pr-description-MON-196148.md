# perf(resources): optimize Resource Status API query performance

## Context

The `GET /monitoring/resources` API (Resource Status page) was taking tens of seconds to respond on large installations, making the UI unusable. This PR addresses the root causes at every layer: SQL generation, indexing strategy, ACL architecture, tag filtering, and pagination.

---

## Changes

### 1. `ORDER BY` fix — `SqlRequestParametersTranslator`

The original code unconditionally added `col IS NULL, col ASC/DESC` for all sort directions. For `DESC`, this generates a computed expression that blocks index usage and forces a full filesort. The `IS NULL` trick is now only applied for `ASC` ordering — MariaDB already puts NULLs last for `DESC`, so the extra expression was both incorrect and harmful.

### 2. New database indexes on `resources`

No indexes existed to efficiently support the common query patterns on a large `resources` table. A virtual column and four covering indexes were added:

- **`is_module`** — virtual column computed from `name LIKE '_Module_%'`, allows the module exclusion filter to be stored in index leaf pages without reading full rows
- **`resources_enabled_status_sort_idx`** `(enabled, status_ordered DESC, last_status_change DESC)` — matches the default sort order, enabling early termination at `LIMIT`
- **`resources_enabled_type_ismodule_idx`** `(enabled, type, is_module, poller_id)` — covering index for unfiltered `COUNT`, avoids all clustered index reads
- **`resources_status_filter_idx`** `(enabled, status, type, is_module, acknowledged, in_downtime, status_confirmed, poller_id)` — status-first covering index for tight seeks on status/state filter queries
- **`resources_name_search_idx`** `(enabled, type, is_module, poller_id, name)` — covering index for name REGEXP/LIKE searches

A migration script was added to `Update-next.php` with idempotent `IF NOT EXISTS` checks.

### 3. `NOT LIKE '_Module_%'` → `is_module = 0`

All occurrences of the `NOT LIKE '_Module_%'` pattern in query conditions were replaced with `resources.is_module = 0`. Pattern matching on every row prevents index usage; the virtual column can be evaluated directly from index leaf pages.

### 4. Direct `COUNT(*)` query — `generateCountResourcesQuery()`

The original count wrapped the full `SELECT` in a subquery: `SELECT COUNT(*) FROM (SELECT ... FROM resources ...)`. This prevents the optimizer from using covering indexes on the inner query — it must materialize all columns before counting. A dedicated `generateCountResourcesQuery()` now emits a flat `SELECT COUNT(*) FROM resources WHERE ...` without any wrapper, allowing covering indexes to be used.

### 5. ACL provider abstraction removed → inline `EXISTS` subqueries

The pluggable `HostACLProvider` / `ServiceACLProvider` / `MetaServiceACLProvider` classes injected SQL fragments that produced near-cartesian-product `LEFT JOIN centreon_acl` conditions. These were replaced with two inline `EXISTS` subqueries (one for hosts, one for services). They are intentionally kept **separate**: merging them into a single `EXISTS` with an `OR` inside degrades the ACL composite index from `key_len=13` (full key seek per row) to `key_len=4` (full group scan per row). The ACL provider abstraction was originally designed for module-contributed resource types, which is no longer needed.

### 6. Adaptive ACL strategy: `EXISTS` vs. pre-computed CTE

For non-admin users, the ACL filtering strategy now adapts based on the number of ACL entries for the user's access groups:

- **Below `ACL_CTE_THRESHOLD` (20 000 entries):** a `WITH acl_accessible` CTE is pre-computed by joining from `centreon_acl` to `resources` (ACL-driven). The main query then drives from this small set via `INNER JOIN`, avoiding per-row correlated subquery evaluation entirely.
- **Above the threshold:** ACL selectivity is high enough that the correlated `EXISTS` approach is faster — the sort-index scan early-terminates at `LIMIT` after seeing few rows.

The threshold check result is cached within the request to avoid repeated `COUNT(*)` queries to `centreon_acl`.

### 7. `parent_resource LEFT JOIN` elimination for COUNT

Fields like `h.alias`, `h.address`, and `parent_alias` normally resolve via a `LEFT JOIN resources parent_resource`. For `COUNT`, this join forces thousands of random disk I/Os over hundreds of thousands of rows. The count query now temporarily substitutes inline approximations (`parent_alias → resources.parent_name`, `h.address → resources.address`, etc.), eliminating the join entirely. The original concordances are restored immediately after via a `finally` block. The data query retains the full `JOIN` and returns exact results — pagination may have minor rounding for the 8-column search bar case, but displayed results are always correct.

### 8. Tag filter pre-join strategy for paginated data queries

Tag/group filters previously used correlated `EXISTS` subqueries, which MariaDB executes as a dependent subquery evaluated per row — scanning the entire table regardless of result size. `EXISTS(UNION ALL)` was tested as an alternative but rejected: MariaDB still executes it as a correlated subquery, evaluating it per outer row.

The data query (`find()`) now uses a **pre-join** strategy via `createTagFilterPreJoinClauses()`: one `INNER JOIN` per active tag type against a small materialized derived table of matching resource IDs. MariaDB materializes the small tag sets first, then does primary key lookups on matching resources only — scaling with result count rather than table size.

The export iterators (`iterateResources`, `iterateResourcesByAccessGroupIds`) retain the CTE approach since they fetch large result sets where pre-filtering is more efficient.

### 9. `FORCE INDEX (resources_status_filter_idx)` for deep pages with status/state filters

With the sort index, page 1 of a status/state filter query benefits from early termination. However, deeper pages must skip all preceding rows — if many of those rows share the same `status_ordered` value and are cold in the buffer pool, this causes massive random I/O. When status/state filters are active in the paginated data query, `FORCE INDEX (resources_status_filter_idx)` is applied instead. This index seeks directly to the actually matching rows, performs a trivial filesort on them, then applies `OFFSET/LIMIT` — uniformly fast for any page depth.

### 10. `UNION` → `UNION ALL` in tag CTEs + filter pushdown

The tag CTEs (`host_groups`, `host_categories`) used `UNION` between two structurally disjoint branches (host resources vs. child service resources via parent join). Since the branches can never produce the same row, the deduplication temp table and filesort were pure overhead. Changed to `UNION ALL`, and `is_module = 0 AND type != 3` filters were pushed inside each branch to reduce the number of rows materialized.

### 11. Cursor-based (keyset) pagination

A new `ResourceCursor` model introduces O(1) deep pagination as an alternative to `LIMIT/OFFSET`. With offset-based pagination, the database must scan and discard all rows up to the requested offset — getting increasingly expensive for deep pages regardless of indexes.

The cursor encodes the sort-column values of the last item on the current page plus a `resource_id` tiebreaker. The repository uses these values to build a keyset `WHERE` condition:

- **Same-direction sorts** (the common case): uses a row value comparison `(col1, col2, ..., resource_id) < (v1, v2, ..., rid)`, which MariaDB resolves as a single range predicate on the composite index — a direct O(log N) seek to the cursor position.
- **Mixed-direction sorts** (fallback): uses the equivalent OR-expanded form.

The cursor token is opaque (base64-encoded JSON), decoded server-side by `ResourceCursor::decode()`. No `COUNT` query is issued for cursor-paginated requests since the total is not needed. The frontend was updated to send the cursor token on subsequent pages and receive the next-page token from the response.

---

## Files Changed

| File | Change |
|---|---|
| `DbReadResourceRepository.php` | Core query logic — all SQL generation changes |
| `SqlRequestParametersTranslator.php` | `ORDER BY DESC` fix |
| `ResourceCursor.php` *(new)* | Keyset cursor model |
| `ResourceACLProviders/*` *(deleted)* | Replaced by inline `EXISTS` subqueries |
| `createTablesCentstorage.sql` | New indexes added to table definition |
| `Update-next.php` | Migration script for existing installations |
| `services.yaml`, `Centreon.yaml` | Removed ACL provider DI wiring |
| Frontend (`useLoadResources`, `listingAtoms`, etc.) | Cursor token handling |
