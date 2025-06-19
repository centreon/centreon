<?php

declare(strict_types=1);

namespace App\ResourceConfiguration\Infrastructure\ApiPlatform\Transformer;

use App\ResourceConfiguration\Domain\Aggregate\ServiceCategory;
use App\ResourceConfiguration\Infrastructure\ApiPlatform\Resource\ServiceCategoryResource;
use App\Shared\Infrastructure\ApiPlatform\Transformer\TransformerInterface;

/**
 * @implements TransformerInterface<ServiceCategory, ServiceCategoryResource>
 */
final readonly class ServiceCategoryTransformer implements TransformerInterface
{
    public function toResource(object $model): object
    {
        return new ServiceCategoryResource(
            id: $model->id()->value,
            name: $model->name()->value,
            alias: $model->alias()->value,
            isActivated: $model->isActivated(),
        );
    }
}
