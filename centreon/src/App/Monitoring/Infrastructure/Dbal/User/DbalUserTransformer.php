<?php

declare(strict_types=1);

namespace App\Monitoring\Infrastructure\Dbal\User;

use App\Monitoring\Domain\Aggregate\User\User;
use App\Monitoring\Domain\Aggregate\User\UserAlias;
use App\Monitoring\Domain\Aggregate\User\UserId;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @phpstan-import-type RowTypeAlias from DbalUserRepository
 * @implements TransformerInterface<RowTypeAlias, User>
 */
final readonly class DbalUserTransformer implements TransformerInterface
{
    /**
     * @param RowTypeAlias $from
     */
    public function transform(mixed $from): User
    {
        return new User(
            new UserId($from['user_id']),
            new UserAlias($from['user_alias']),
        );
    }
}
