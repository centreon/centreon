<?php

declare(strict_types=1);

namespace App\Monitoring\Infrastructure\Dbal\HostGroup;

use App\Monitoring\Domain\Aggregate\HostGroup\HostGroup;
use App\Monitoring\Domain\Aggregate\Notification\NotificationId;
use App\Monitoring\Domain\Repository\HostGroupRepository;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{
 *    hostgroup_id: int,
 *    hostgroup_name: string,
 * }
 */
final readonly class DbalHostGroupRepository extends DbalRepository implements HostGroupRepository
{
    public const string TABLE_NAME = 'hostgroup';
    public const string NOTIFICATION_RELATION_TABLE_NAME = 'notification_hg_relation';

    /**
     * @param TransformerInterface<RowTypeAlias, HostGroup> $transformer
    */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        #[Autowire(service: DbalHostGroupTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    /**
     * @return array<string>
     */
    public static function getSelectColumns(string $alias = 'hg'): array
    {
        return [
            "{$alias}.hg_id AS hostgroup_id",
            "{$alias}.hg_name AS hostgroup_name",
        ];
    }

    public function findByNotificationId(NotificationId $id): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(...self::getSelectColumns('hg'))
            ->from(self::TABLE_NAME, 'hg')
            ->innerJoin(
                'hg',
                self::NOTIFICATION_RELATION_TABLE_NAME,
                'n',
                'hg.hg_id = n.hg_id'
            )
            ->where('n.notification_id = :id')
            ->setParameter('id', $id->value);

        /** @var RowTypeAlias|false $row */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return array_map(fn (array $row): HostGroup => $this->transformer->transform($row), $rows);
    }
}
