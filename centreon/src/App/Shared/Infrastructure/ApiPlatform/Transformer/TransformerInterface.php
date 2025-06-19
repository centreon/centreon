<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\ApiPlatform\Transformer;

/**
 * @template TModel of object
 * @template TResource of object
 */
interface TransformerInterface
{
    /**
     * @param TModel $model
     *
     * @return TResource
     */
    public function toResource(object $model): object;
}
