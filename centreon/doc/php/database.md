# Database access outside `src/App` — examples

This page shows how to apply the database records outside `src/App`. The examples use a fictitious `gadget` table.

- **All scopes:** [ADR-0010](adr/0010-apip-core-legacy-parameterize-repository-queries.md) (bound values, identifier allowlist, `LIKE` escaping).
- **`src/Core`:** [ADR-0027](adr/0027-core-reach-the-database-from-src-core-through-connection-interface.md), [ADR-0029](adr/0029-core-make-src-core-repositories-throw-repository-exception.md), [ADR-0050](adr/0050-core-extend-database-repository-in-every-new-src-core-repository.md).
- **Legacy:** [ADR-0028](adr/0028-legacy-reach-the-database-from-legacy-code-through-connection-interface.md), [ADR-0030](adr/0030-legacy-make-legacy-repositories-throw-repository-exception.md), [ADR-0051](adr/0051-legacy-extend-database-repository-in-every-new-legacy-repository.md).
- **Secret writes:** [ADR-0048](adr/0048-core-drop-the-driver-exception-when-a-repository-write-of-a-secret-fails.md) for `src/Core`, [ADR-0049](adr/0049-legacy-drop-the-driver-exception-when-a-repository-write-of-a-secret-fails.md) for legacy code.

---

## Table of contents

1. [Core repository](#1-core-repository)
2. [Legacy code](#2-legacy-code)
3. [Repository failure](#3-repository-failure)

---

## 1. Core repository

A repository extending `DatabaseRepository` reaches its connection through `$this->connection`. In an `AbstractRepositoryRDB` or `AbstractRepositoryDRB` repository, the connection is `$this->db`.

```php
// SELECT one row
$queryBuilder = $this->connection->createQueryBuilder();
$query = $queryBuilder->select('g.id', 'g.name')
    ->from('`:db`.`gadget`', 'g')
    ->where($queryBuilder->expr()->equal('g.id', ':gadgetId'))
    ->getQuery();

$row = $this->connection->fetchAssociative(
    $this->translateDbName($query),
    QueryParameters::create([QueryParameter::int('gadgetId', $gadgetId)]),
);

// UPDATE
$queryBuilder = $this->connection->createQueryBuilder();
$query = $queryBuilder->update('`:db`.`gadget`')
    ->set('name', ':name')
    ->set('is_enabled', ':isEnabled') // a TINYINT(1) column
    ->where($queryBuilder->expr()->equal('id', ':gadgetId'))
    ->getQuery();

$this->connection->update(
    $this->translateDbName($query),
    QueryParameters::create([
        QueryParameter::string('name', $name),
        QueryParameter::bool('isEnabled', $isEnabled),
        QueryParameter::int('gadgetId', $gadgetId),
    ]),
);

// IN list (the repository uses SqlMultipleBindTrait)
if ($gadgetIds === []) {
    return;
}
['parameters' => $parameters, 'placeholderList' => $placeholders] = $this->createMultipleBindParameters(
    $gadgetIds,
    'gadgetId',
    QueryParameterTypeEnum::INTEGER,
);
$queryBuilder = $this->connection->createQueryBuilder();
$query = $queryBuilder->delete('`:db`.`gadget`')
    ->where($queryBuilder->expr()->in('id', $placeholders))
    ->getQuery();

$this->connection->delete($this->translateDbName($query), QueryParameters::create($parameters));

// LIKE, escaped as set by ADR-0010
$queryBuilder = $this->connection->createQueryBuilder();
$query = $queryBuilder->select('g.id')
    ->from('`:db`.`gadget`', 'g')
    ->where($queryBuilder->expr()->like('g.name', ':search'))
    ->getQuery();

$ids = $this->connection->fetchFirstColumn(
    $this->translateDbName($query),
    QueryParameters::create([
        QueryParameter::string('search', '%' . addcslashes($search, '%_\\') . '%'),
    ]),
);
```

---

## 2. Legacy code

A legacy function or class takes a `ConnectionInterface`, such as `$pearDB`. A new legacy repository file extends `DatabaseRepository` and is written as in [§1](#1-core-repository).

```php
// SELECT one value
function findGadgetName(ConnectionInterface $connection, int $gadgetId): ?string
{
    $queryBuilder = $connection->createQueryBuilder();
    $query = $queryBuilder->select('g.name')
        ->from('gadget', 'g')
        ->where($queryBuilder->expr()->equal('g.id', ':gadgetId'))
        ->getQuery();

    $name = $connection->fetchOne($query, QueryParameters::create([QueryParameter::int('gadgetId', $gadgetId)]));

    return $name === false ? null : (string) $name;
}

$name = findGadgetName($pearDB, $gadgetId);

// IN list
require_once _CENTREON_PATH_ . 'www/include/common/sqlCommonFunction.php';

if ($gadgetIds === []) {
    return;
}
['parameters' => $parameters, 'placeholderList' => $placeholders] = createMultipleBindParameters(
    $gadgetIds,
    'gadgetId',
    QueryParameterTypeEnum::INTEGER,
);
$queryBuilder = $pearDB->createQueryBuilder();
$query = $queryBuilder->delete('gadget')
    ->where($queryBuilder->expr()->in('id', $placeholders))
    ->getQuery();

$pearDB->delete($query, QueryParameters::create($parameters));

// LIKE, escaped as set by ADR-0010
$queryBuilder = $pearDB->createQueryBuilder();
$query = $queryBuilder->select('g.id')
    ->from('gadget', 'g')
    ->where($queryBuilder->expr()->like('g.name', ':search'))
    ->getQuery();

$ids = $pearDB->fetchFirstColumn(
    $query,
    QueryParameters::create([
        QueryParameter::string('search', '%' . addcslashes($search, '%_\\') . '%'),
    ]),
);
```

---

## 3. Repository failure

The repository wraps the caught infrastructure exception in a `Core\Common\Domain\Exception\RepositoryException`, with its business message, context and `previous`; a new method throws this one, under an alias when the class already imports the deprecated `Centreon\Domain\Repository\RepositoryException`, which its existing methods keep throwing. A method that writes a [secret](glossary.md#secret) drops `previous` and uses a generic message ([ADR-0048](adr/0048-core-drop-the-driver-exception-when-a-repository-write-of-a-secret-fails.md), [ADR-0049](adr/0049-legacy-drop-the-driver-exception-when-a-repository-write-of-a-secret-fails.md)).

```php
try {
    $queryBuilder = $this->connection->createQueryBuilder();
    $query = $queryBuilder->delete('`:db`.`gadget`')
        ->where($queryBuilder->expr()->equal('id', ':gadgetId'))
        ->getQuery();

    $this->connection->delete(
        $this->translateDbName($query),
        QueryParameters::create([QueryParameter::int('gadgetId', $gadgetId)]),
    );
} catch (QueryBuilderException|CollectionException|ValueObjectException|ConnectionException $exception) {
    throw new RepositoryException(
        message: 'Error while deleting the gadget',
        context: ['gadget_id' => $gadgetId],
        previous: $exception,
    );
}
```
