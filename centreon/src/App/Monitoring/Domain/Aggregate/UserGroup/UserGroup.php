<?php

declare(strict_types=1);

namespace App\Monitoring\Domain\Aggregate\UserGroup;

use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * @extends AggregateRoot<UserGroupId>
 */
final class UserGroup extends AggregateRoot {
    public function __construct(?UserGroupId $id, public readonly UserGroupName $name)
    {
        parent::__construct($id);
    }
}
