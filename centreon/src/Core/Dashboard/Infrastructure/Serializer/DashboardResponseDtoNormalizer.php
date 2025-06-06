<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Dashboard\Infrastructure\Serializer;

use Core\Dashboard\Application\UseCase\FindFavoriteDashboards\Response\DashboardResponseDto;
use Core\Dashboard\Infrastructure\Model\DashboardSharingRoleConverter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class DashboardResponseDtoNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $normalizer,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @param mixed $data
     * @param ?string $format
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof DashboardResponseDto;
    }

    /**
     * @param DashboardResponseDto $object
     * @param string|null $format
     * @param array<string, mixed> $context
     *
     * @throws \Throwable
     *
     * @return array<string, mixed>|bool|float|int|string|null
     */
    public function normalize(mixed $object, ?string $format = null, array $context = [])
    {
        /** @var array<string, bool|float|int|string> $data */
        $data = $this->normalizer->normalize($object, $format, $context);
        $data['own_role'] = DashboardSharingRoleConverter::toString($object->ownRole);

        /** array{
         *     contacts: array<array{
         *      id: int,
         *      name: string,
         *      email: string,
         *      role: DashboardSharingRole
         *     }>,
         *     contact_groups: array<array{
         *      id: int,
         *      name: string,
         *      role: DashboardSharingRole
         *     }>
         * } $shares
         */
        $shares = $object->shares;

        foreach ($shares['contacts'] as $key => $contact) {
            $data['shares']['contacts'][$key]['role'] = DashboardSharingRoleConverter::toString($contact['role']);
        }

        foreach ($shares['contact_groups'] as $key => $contactGroup) {
            $data['shares']['contact_groups'][$key]['role'] = DashboardSharingRoleConverter::toString($contactGroup['role']);
        }

        return $data;
    }

    /**
     * @param ?string $format
     * @return array<class-string|'*'|'object'|string, bool|null>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            DashboardResponseDto::class => true,
        ];
    }
}
