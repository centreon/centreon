<?php

declare(strict_types=1);

namespace App\Monitoring\Infrastructure\Dbal\User;

use App\Monitoring\Domain\Aggregate\Notification\NotificationId;
use App\Monitoring\Domain\Aggregate\User\User;
use App\Monitoring\Domain\Aggregate\User\UserId;
use App\Monitoring\Domain\Repository\UserRepository;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{
 *    user_id: int,
 *    user_alias: string,
 * }
 */
final readonly class DbalUserRepository extends DbalRepository implements UserRepository
{
    public const string TABLE_NAME = 'contact';
    public const string NOTIFICATION_RELATION_TABLE_NAME = 'notification_user_relation';

    /**
     * @param Connection $connection
     * @param TransformerInterface<RowTypeAlias, User> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        #[Autowire(service: DbalUserTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    /**
     * @return array<string>
     */
    public static function getSelectColumns(string $alias = 'u'): array
    {
        return [
            "{$alias}.contact_id AS user_id",
            "{$alias}.contact_alias AS user_alias",
        ];
    }

    public function findByNotification(NotificationId $id): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(...self::getSelectColumns('u'))
            ->from(self::TABLE_NAME, 'u')
            ->innerJoin(
                'u',
                self::NOTIFICATION_RELATION_TABLE_NAME,
                'r',
                'u.contact_id = r.user_id'
            )
            ->where('r.notification_id = :id')
            ->setParameter('id', $id->value);

        /** @var RowTypeAlias|false $row */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return array_map($this->transformer->transform(...), $rows);
    }

    public function get(UserId $id): User
    {
        // TODO: Implement get() method.
    }
}
