<?php

declare(strict_types=1);

namespace App\Monitoring\Domain\Aggregate\User;

use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * @extends AggregateRoot<UserId>
 */
final class User extends AggregateRoot
{
    public function __construct(
        ?UserId $id,
        public readonly UserAlias $name,
    )
    {
        parent::__construct($id);
    }
}
