<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace App\Shared\Infrastructure\Dbal;

use App\Shared\Domain\Repository\SortableCriteria;
use Doctrine\DBAL\Query\QueryBuilder;

abstract readonly class DbalRepository
{
    final protected function setId(object $object, mixed $id): void
    {
        $reflectionClass = new \ReflectionClass($object);

        while ($reflectionClass) {
            if ($reflectionClass->hasProperty('id')) {
                $reflectionProperty = $reflectionClass->getProperty('id');
                $reflectionProperty->setAccessible(true);

                if ($reflectionProperty->isStatic()) {
                    throw new \LogicException(\sprintf('Property "%s::$id" must not be static.', $reflectionClass->getName()));
                }

                if ($reflectionProperty->isReadOnly()) {
                    throw new \LogicException(\sprintf('Cannot set readonly property "%s::$id".', $reflectionClass->getName()));
                }

                $reflectionProperty->setValue($object, $id);

                return;
            }

            $reflectionClass = $reflectionClass->getParentClass();
        }

        throw new \LogicException(\sprintf('Property "id" not found in "%s" hierarchy.', $object::class));
    }

    /**
     * Hydrate a "to many" relation of a primary entity.
     *
     * @template TPrimary of object
     * @template TRelated of object
     * @template TRelatedRow of array<string, mixed>
     *
     * @param TPrimary $primaryEntity The entity to be hydrated
     * @param array<TRelatedRow> $relatedRows Raw joined rows
     * @param callable(TRelatedRow): TRelated $relatedFactoryCallback Callback to create related entity
     * @param callable(TPrimary, TRelated): void $relationCallback Callback to set relationship
     */
    final protected function hydrateToManyRelation(
        object $primaryEntity,
        array $relatedRows,
        string $relatedIdKey,
        callable $relatedFactoryCallback,
        callable $relationCallback,
    ): void {
        $relatedEntities = [];

        foreach ($relatedRows as $row) {
            $relatedId = $row[$relatedIdKey];

            $relatedEntities[$relatedId] ??= $relatedFactoryCallback($row);
            $relationCallback($primaryEntity, $relatedEntities[$relatedId]);
        }
    }

    final protected function sort(QueryBuilder $qb, string $alias, SortableCriteria $criteria): void
    {
        if ($criteria->getSort() !== []) {
            foreach ($criteria->getSort() as $field => $direction) {
                $qb->addOrderBy("{$alias}." . $criteria->getFieldMapping()[$field] ?? $field, $direction->value);
            }
        }
    }
}
