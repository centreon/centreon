<?php

declare(strict_types=1);

namespace App\Monitoring\Infrastructure\Dbal\ServiceGroup;

use App\Monitoring\Domain\Aggregate\HostGroup\HostGroup;
use App\Monitoring\Domain\Aggregate\Notification\NotificationId;
use App\Monitoring\Domain\Aggregate\ServiceGroup\ServiceGroup;
use App\Monitoring\Domain\Repository\ServiceGroupRepository;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{
 *    servicegroup_id: int,
 *    servicegroup_name: string,
 * }
 */
readonly class DbalServiceGroupRepository extends DbalRepository implements ServiceGroupRepository
{
    public const string TABLE_NAME = 'servicegroup';
    public const string NOTIFICATION_RELATION_TABLE_NAME = 'notification_sg_relation';

    /**
     * @param TransformerInterface<RowTypeAlias, HostGroup> $transformer
    */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        #[Autowire(service: DbalServiceGroupTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    /**
     * @return array<string>
     */
    public static function getSelectColumns(string $alias = 'sg'): array
    {
        return [
            "{$alias}.sg_id AS servicegroup_id",
            "{$alias}.sg_name AS servicegroup_name",
        ];
    }

    public function findByNotificationId(NotificationId $id): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(...self::getSelectColumns('sg'))
            ->from(self::TABLE_NAME, 'sg')
            ->innerJoin(
                'sg',
                self::NOTIFICATION_RELATION_TABLE_NAME,
                'n',
                'sg.sg_id = n.sg_id'
            )
            ->where('n.notification_id = :id')
            ->setParameter('id', $id->value);

        /** @var RowTypeAlias|false $row */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return array_map(fn (array $row): ServiceGroup => $this->transformer->transform($row), $rows);
    }
}
