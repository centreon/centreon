<?php

declare(strict_types=1);

namespace App\Monitoring\Infrastructure\Dbal\UserGroup;

use App\Monitoring\Domain\Aggregate\Notification\NotificationId;
use App\Monitoring\Domain\Aggregate\UserGroup\UserGroup;
use App\Monitoring\Domain\Repository\UserGroupRepository;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{
 *    usergroup_id: int,
 *    usergroup_name: string,
 * }
 */
readonly class DbalUserGroupRepository extends DbalRepository implements UserGroupRepository
{
    public const string TABLE_NAME = 'contactgroup';
    public const string NOTIFICATION_RELATION_TABLE_NAME = 'notification_contactgroup_relation';

    /**
     * @param TransformerInterface<RowTypeAlias, UserGroup> $transformer
    */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        #[Autowire(service: DbalUserGroupTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    /**
     * @return array<string>
     */
    public static function getSelectColumns(string $alias = 'cg'): array
    {
        return [
            "{$alias}.cg_id AS usergroup_id",
            "{$alias}.cg_name AS usergroup_name",
        ];
    }

    public function findByNotification(NotificationId $id): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(...self::getSelectColumns('cg'))
            ->from(self::TABLE_NAME, 'cg')
            ->innerJoin(
                'cg',
                self::NOTIFICATION_RELATION_TABLE_NAME,
                'n',
                'cg.cg_id = n.contactgroup_id'
            )
            ->where('n.notification_id = :id')
            ->setParameter('id', $id->value);

        /** @var RowTypeAlias|false $row */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return array_map(fn (array $row): UserGroup => $this->transformer->transform($row), $rows);
    }
}
