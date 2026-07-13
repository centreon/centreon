<?php

declare(strict_types=1);

namespace App\Monitoring\Infrastructure\Dbal\UserGroup;

use App\Monitoring\Domain\Aggregate\UserGroup\UserGroup;
use App\Monitoring\Domain\Aggregate\UserGroup\UserGroupId;
use App\Monitoring\Domain\Aggregate\UserGroup\UserGroupName;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @phpstan-import-type RowTypeAlias from DbalUserGroupRepository
 * @implements TransformerInterface<RowTypeAlias, UserGroup>
 */
final readonly class DbalUserGroupTransformer implements TransformerInterface
{
    /**
     * @param RowTypeAlias $from
     */
    public function transform(mixed $from): UserGroup
    {
        return new UserGroup(
            new UserGroupId($from['usergroup_id']),
            new UserGroupName($from['usergroup_name']),
        );
    }
}
