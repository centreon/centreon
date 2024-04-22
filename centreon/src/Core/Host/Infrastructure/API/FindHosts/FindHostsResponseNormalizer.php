<?php

namespace Core\Host\Infrastructure\API\FindHosts;

use Core\Host\Application\UseCase\FindHosts\FindHostsResponse;
use Core\Host\Application\UseCase\FindHosts\HostDto;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * @method array getSupportedTypes(?string $format)
 */
class FindHostsResponseNormalizer implements NormalizerInterface
{

    public function __construct(private readonly ObjectNormalizer $normalizer)
    {
    }
    /**
     * @inheritDoc
     */
    public function normalize(mixed $object, ?string $format = null, array $context = [])
    {
        return [
            'result' => array_map(
                function (HostDto $hostDto) {
                    return $this->normalizer->normalize($hostDto, null, ['groups' => $context['groups']]);
                },
                $object->hostDto
            ),
            'meta' => $context['request_parameters']
        ];
    }

    /**
     * @inheritDoc
     */
    public function supportsNormalization(mixed $data, ?string $format = null)
    {
        return $data instanceof FindHostsResponse;
    }

    public function __call(string $name, array $arguments)
    {
        // TODO: Implement @method array getSupportedTypes(?string $format)
    }
}