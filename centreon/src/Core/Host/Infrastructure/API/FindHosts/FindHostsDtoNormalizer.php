<?php

namespace Core\Host\Infrastructure\API\FindHosts;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @method array getSupportedTypes(?string $format)
 */
class FindHostsDtoNormalizer implements NormalizerInterface
{

    /**
     * @inheritDoc
     */
    public function normalize(mixed $object, ?string $format = null, array $context = [])
    {
        // TODO: Implement normalize() method.
    }

    /**
     * @inheritDoc
     */
    public function supportsNormalization(mixed $data, ?string $format = null)
    {
        // TODO: Implement supportsNormalization() method.
    }

    public function __call(string $name, array $arguments)
    {
        // TODO: Implement @method array getSupportedTypes(?string $format)
    }
}