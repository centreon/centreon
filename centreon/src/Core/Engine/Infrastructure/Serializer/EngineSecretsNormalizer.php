<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Core\Engine\Infrastructure\Serializer;

use Core\Engine\Domain\Model\EngineSecrets;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class EngineSecretsNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $normalizer,
    ) {
    }

    /**
     * @param EngineSecrets $object
     * @param string|null $format
     * @param array<string, mixed> $context
     *
     * @throws \Throwable
     *
     * @return array<string, mixed>
     */
    public function normalize(
        mixed $object,
        ?string $format = null,
        array $context = [],
    ): array {
        /** @var array{app_secret: array{key: string}, salt: array{key: string}} $data */
        $data = $this->normalizer->normalize($object, $format, $context);

        return array_map(fn ($property) => $property['key'], $data);
    }

    /**
     * @param array<string, mixed> $context
     * @param mixed $data
     * @param ?string $format
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof EngineSecrets;
    }

    /**
     * @param ?string $format
     * @return array<class-string|'*'|'object'|string, bool|null>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            EngineSecrets::class => true,
        ];
    }
}
