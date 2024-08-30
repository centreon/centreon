<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace CentreonOpenTickets\Resources\Infrastructure\API;

use Core\Infrastructure\Common\Presenter\PresenterTrait;
use Core\Resources\Infrastructure\API\ExtraDataNormalizer\ExtraDataNormalizerInterface;

final class TicketExtraDataFormatter implements ExtraDataNormalizerInterface

{
    use PresenterTrait;

    private const EXTRA_DATA_SOURCE_NAME = 'open_tickets';

    /**
     * @inheritDoc
     */
    public function getExtraDataSourceName(): string
    {
        return self::EXTRA_DATA_SOURCE_NAME;
    }

    /**
     * @inheritDoc
     */
    public function normalizeExtraDataForResource(mixed $data): array
    {
        /** @var array{id:int, subject:string, created_at:\DateTimeInterface} $data */
        return [
            'tickets' => [
                'id' => $data['id'],
                'subject' => $data['subject'],
                'created_at' => $this->formatDateToIso8601($data['created_at'])
            ]
        ];
    }

    /**
     * @inheritDoc
     */
    public function isValidFor(string $providerName): bool
    {
        return $providerName === self::EXTRA_DATA_SOURCE_NAME;
    }
}
