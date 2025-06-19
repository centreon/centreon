<?php

declare(strict_types=1);

namespace App\ResourceConfiguration\Infrastructure\Doctrine;

use App\ResourceConfiguration\Domain\Aggregate\ServiceCategory;
use App\ResourceConfiguration\Domain\Aggregate\ServiceCategoryId;
use App\ResourceConfiguration\Domain\Aggregate\ServiceCategoryName;
use App\ResourceConfiguration\Domain\Repository\ServiceCategoryRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type _ServiceCategoryRow = array{sc_id: int, sc_name: string, sc_description: string, sc_activate: '0'|'1'}
 */
final readonly class DoctrineServiceCategoryRepository implements ServiceCategoryRepository
{
    private const TABLE_NAME = 'service_categories';

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
    ) {
    }

    public function add(ServiceCategory $serviceCategory): void
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->insert(self::TABLE_NAME)
            ->values([
                'sc_name' => $serviceCategory->name()->value,
                'sc_description' => $serviceCategory->alias()->value,
                'sc_activate' => $serviceCategory->isActivated() ? '1' : '0',
            ])
            ->executeStatement();

        /** @var int $id */
        $id = $this->connection->lastInsertId();

        $this->setId($serviceCategory, new ServiceCategoryId($id));
    }

    public function findOneByName(ServiceCategoryName $name): ?ServiceCategory
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('sc_id', 'sc_name', 'sc_description', 'sc_activate')
            ->from(self::TABLE_NAME)
            ->where('sc_name = :name')
            ->setParameter('name', $name->value)
            ->setMaxResults(1);

        /** @var _ServiceCategoryRow|false */
        $row = $qb->fetchOne();

        if (!$row) {
            return null;
        }

        $serviceCategory = new ServiceCategory(
            name: $row['name'],
            alias: $row['alias'],
            activated: '1' === $row['activated'] ? true : false,
        );

        $this->setId($serviceCategory, new ServiceCategoryId($row['id']));

        return $serviceCategory;
    }

    private function setId(ServiceCategory $serviceCategory, ServiceCategoryId $id): void
    {
        $r = new \ReflectionProperty($serviceCategory, 'id');
        $r->setAccessible(true)->setValue($serviceCategory, $id);
    }
}
