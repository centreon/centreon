# ACL computation dataset

A reproducible dataset for `cron/centAcl.php`: it is loaded into a database that holds no other ACL data, the engine computes `centreon_acl`, and the rows of the dataset access groups are compared with a frozen expected file. The same dataset on the same engine always gives the same rows: any difference is a behavior change.

## Dataset S

`php tests/acl/gen.php 2500 20`: 2,500 hosts, 25,000 services, 20 access groups, 40 resource accesses, 97,376 `centreon_acl` rows. Large enough for the CI to show the time and memory differences of the engine, short enough to run on every pull request.

`expected-S.sql` was frozen with the original `centAcl.php`, before the engine was sped up: the current engine must give the same rows as the original one.

## Files

| File             | Content                                                                                                     |
| ---------------- | ----------------------------------------------------------------------------------------------------------- |
| `gen.php`        | Dataset generator. No randomness: same arguments, same SQL                                                  |
| `ids.php`        | The fixed id bases of the generated objects, shared by `gen.php` and `qualify.php`                          |
| `qualify.php`    | `load` a dataset, `export` the computed rows, `check` them against an expected file, run the `scenarios`    |
| `measure.php`    | Prepended to `centAcl.php` by `scenarios` to measure its PHP peak memory, its max resident size, its writes |
| `dataset-S.sql`  | The dataset                                                                                                 |
| `expected-S.sql` | The `centreon_acl` rows that `centAcl.php` computes for it                                                  |

## Running the scenarios

The database must hold no object in the dataset id ranges (`ids.php`), for example a schema created by `tests/php/integration-bootstrap.php`, and nothing else may run `centAcl.php` at the same time (no `crond`).

```shell
php tests/acl/qualify.php scenarios S    # exit 0: every check passed, 1: a check failed, 2: an error
```

`scenarios` removes any previous dataset, loads `dataset-S.sql`, flags every access group and resource of the dataset as changed before each run, and runs `cron/centAcl.php`, unmodified, in eight scenarios:

| Scenario                             | What happens before the run                                                                                                                                                                                                    | Rows check                                                                                                                                                              | Writes check                                                    |
| ------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------- |
| **A. Empty table**                   | the dataset was just loaded                                                                                                                                                                                                    | the rows equal `expected-S.sql`                                                                                                                                         | every row inserted, none deleted                                |
| **B. Nothing changed**               | the table already holds the right rows                                                                                                                                                                                         | the rows still equal `expected-S.sql`                                                                                                                                   | every group skipped thanks to its fingerprint, no row written   |
| **C. Small change**                  | 5 hosts removed from and 20 hosts added to the first host group                                                                                                                                                                | the rows equal those of a full recompute on an empty table                                                                                                              | only the changed rows written, the unchanged groups skipped     |
| **D. Rows deleted outside the cron** | the rows of the dataset groups deleted, their fingerprints kept (a support procedure does that)                                                                                                                                | the rows equal those of C                                                                                                                                               | every row written again                                         |
| **E. Row inserted outside the cron** | a row a group is not granted inserted, and its fingerprint removed (the other writers do that)                                                                                                                                 | the rows equal those of C                                                                                                                                               | only that group written, the row deleted                        |
| **F. Disabled access group**         | one access group disabled                                                                                                                                                                                                      | its rows and its fingerprint removed, the other rows equal those of C, the groups of `centreon_acl` listed through its unique key (`EXPLAIN`)                           | every other group skipped, no row written                       |
| **G. No fingerprint table**          | the fingerprint table renamed, as before the database upgrade creates it                                                                                                                                                       | the rows equal those of C                                                                                                                                               | no group skipped, no row written                                |
| **H. Write error, then retried**     | a check constraint rejects a row of a group that shares a resource access with the group written before it, the resource accesses flagged as "Reload ACL" does; the constraint and the shared resource access are then removed | the group keeps its rows, its resource accesses stay flagged (the shared one included), the lock of the cron is released; after the next run, the rows equal those of C | only that group written by the next run, its row inserted again |

The writes check reads what the engine did (`$aclRunStats`, written by `measure.php`): a run that reports nothing fails. After each run, `scenarios` also checks that no dataset group is still flagged: `centAcl.php` exits with code 0 even when it stops on an error. It prints a table with the time, the PHP peak memory, the max resident size, the writes and the number of rows checked of each run, as plain text, and appends it in Markdown to the GitHub job summary when `$GITHUB_STEP_SUMMARY` is set.

The rows check loads the expected rows into a temporary `centreon_storage.centreon_acl_expected` table and compares it with `centreon_acl` both ways with `EXCEPT ALL`, which also compares the number of copies of each row (a host row can be written twice: its NULL service id escapes the unique key). On a difference, the unexpected rows (visibility leak) and the missing rows (lost visibility) are printed.

## Comparing with another engine

Measured alone, a time says nothing: it depends on the machine. With `--baseline`, the cron scripts of another directory (in CI, those of the target branch) and the current ones are measured on the same machine, right after the checks above. Both run from a temporary copy of the Centreon root built the same way: the paths of the files PHP loads take memory, which must not differ between them.

```shell
php tests/acl/qualify.php scenarios S --baseline /path/to/other/centreon/cron \
  --baseline-ref develop --baseline-commit "$(git rev-parse develop)"
```

- Scenarios B and C run 3 times with each engine, alternately, so that both meet the same database and machine conditions; the median is kept.
- The step fails when this engine is more than 20 % slower than the baseline, or uses more than 5 % more PHP memory, in B or C. The PHP memory is the memory really used by PHP (`memory_get_peak_usage()`), which does not depend on the machine: its margin only absorbs the code a pull request adds to the files the cron loads.
- The other scenarios are not compared: they are rare (first run, support procedure, upgrade), and writing every row in one transaction per group makes A and D slightly slower than deleting and re-inserting every row.
- `--baseline-ref` and `--baseline-commit` are optional: they name the branch and the commit the baseline comes from, in the report.
- The report starts with the comparison: for B and C, the time and the PHP peak memory of the baseline and of this branch side by side, the change (gains in bold in the job summary), and a legend on where the baseline comes from. The table of the eight scenarios follows, then the list of what makes the step fail.
- Every measured run is printed on stderr, one line per round with both engines, to see the dispersion behind a median.

The lower-level commands are still available:

```shell
php tests/acl/qualify.php load tests/acl/dataset-S.sql
php cron/centAcl.php
php tests/acl/qualify.php check tests/acl/expected-S.sql
```

All the commands use the databases of the Centreon configuration (`config/centreon.config.php`), like `centAcl.php`.

## When the scenarios fail

`scenarios` exits with code 1, and the CI step fails, when:

- the rows computed by this branch differ from the expected ones, in any scenario: a row too many is a visibility leak, a missing row a lost visibility;
- a run writes other rows than needed: every row in A and D, none in B, F and G, only the changed ones in C and E;
- a run skips other groups than expected: all of them in B and F, none in G, all but the changed one in E and H;
- a run reports no writes (`$aclRunStats`): they cannot be checked;
- a disabled access group keeps rows or its fingerprint, or listing the groups of `centreon_acl` reads every row (`EXPLAIN`);
- a group whose write fails loses rows, its resource accesses lose their flag (one it shares with a group written before it included), or `centAcl.php` keeps its lock (scenario H);
- with `--baseline`, this branch is more than 20 % slower than the baseline, or uses more than 5 % more PHP memory, in B or C.

It exits with code 2, the step failing too, when `centAcl.php` exits with an error code, stops on an error outside scenario H and leaves an access group of the dataset unprocessed, or ends without writing its measures, and when the dataset or the expected files are missing.

## In CI

The "Check ACL computation on dataset S" step, last step of the `backend-integration-test` job (`.github/workflows/web.yml`), runs `php tests/acl/qualify.php scenarios S`. It runs after the PHPUnit integration suites, whose bootstrap creates the schema and which leave no ACL data behind.

On a pull request, the step also extracts the `centreon/cron` directory of the target branch and passes it with `--baseline`: a pull request that slows down the ACL computation, or makes it use more memory, fails. On a push, without a target branch, only the checks run.

The report is in the log of the step, as plain-text tables, and in the summary of the workflow run, under the `backend-integration-test` job, as rendered tables. The log ends with the link to that summary.

## Regenerating the files

- `dataset-S.sql`: `php tests/acl/gen.php 2500 20 > tests/acl/dataset-S.sql`, after a change to `gen.php`.
- `expected-S.sql`: load the dataset into an empty database, flag its groups, run the engine, then `php tests/acl/qualify.php export > tests/acl/expected-S.sql`. Do it only when the computed rows are meant to change, in the same pull request as that change: the diff of the file shows the changed rows.

The expected rows describe the behavior of `centAcl.php` when they were frozen, anomalies included: they prove that the result does not change, not that it is right.
