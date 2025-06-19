<?php

declare(strict_types=1);

namespace App\ResourceConfiguration\Domain\Repository;

use App\ResourceConfiguration\Domain\Aggregate\ServiceCategory;
use App\ResourceConfiguration\Domain\Aggregate\ServiceCategoryName;

interface ServiceCategoryRepository
{
    public function add(ServiceCategory $serviceCategory): void;

    public function findOneByName(ServiceCategoryName $name): ?ServiceCategory;
}
