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

namespace Core\Security\Token\Domain\Model;

use Core\Common\Domain\TrimmedString;
use DateTimeImmutable;
use DateTimeInterface;
use Respect\Validation\Exceptions\DateTimeException;

/**
 * @phpstan-type _ApiToken array{
 *      name: string,
 *      user_id: int,
 *      user_name: string,
 *      creator_id: ?int,
 *      creator_name: string,
 *      creation_date: int,
 *      expiration_date: ?int,
 *      token_type: string,
 *      is_revoked: int
 *  }
 *
 * @phpstan-type _JwtToken array{
 *      name: string,
 *      creator_id: ?int,
 *      creator_name: string,
 *      token_type: string,
 *      creation_date: int,
 *      expiration_date: ?int,
 *      is_revoked: int,
 *      token_string:string,
 *      encoding_key: string
 *  }
 *
 * @phpstan-type _Token array{
 *      name: string,
 *      user_id: ?int,
 *      user_name: ?string,
 *      creator_id: ?int,
 *      creator_name: string,
 *      creation_date: int,
 *      expiration_date: ?int,
 *      token_type: string,
 *      is_revoked: int,
 *      token_string: ?string,
 *      encoding_key: ?string
 *  }
 *
 * @phpstan-type _NewToken array{
 *      name: string,
 *      user_id: ?int,
 *      creator_id: int,
 *      creator_name: string,
 *      expiration_date: ?DateTimeInterface,
 *      configuration_provider_id: ?int,
 *  }
 *
 * @phpstan-type _NewJwtToken array{
 *      name: string,
 *      creator_id: int,
 *      creator_name: string,
 *      expiration_date: ?DateTimeInterface,
 *  }
 *
 * @phpstan-type _NewApiToken array{
 *      name: string,
 *      user_id: int,
 *      creator_id: int,
 *      creator_name: string,
 *      expiration_date: ?DateTimeInterface,
 *      configuration_provider_id: int,
 *  }
 */
final class TokenFactory
{
    /**
     * @param TokenTypeEnum $type
     * @param _Token $data
     *
     * @throws DateTimeException
     * @return ApiToken|JwtToken
     */
    public static function create(
        TokenTypeEnum $type,
        array $data
    ): Token
    {
        if ($type === TokenTypeEnum::CMA) {
            /** @var _JwtToken $data */
            return new JwtToken(
                new TrimmedString($data['name']),
                $data['creator_id'],
                new TrimmedString($data['creator_name']),
                (new DateTimeImmutable())->setTimestamp($data['creation_date']),
                $data['expiration_date'] !== null
                    ? (new DateTimeImmutable())->setTimestamp($data['expiration_date'])
                    : null,
                (bool) $data['is_revoked'],
                $data['encoding_key'],
                $data['token_string'],
            );
        }

        /** @var _ApiToken $data */
        return new ApiToken(
            new TrimmedString($data['name']),
            $data['user_id'],
            new TrimmedString($data['user_name']),
            $data['creator_id'],
            new TrimmedString($data['creator_name']),
            (new DateTimeImmutable())->setTimestamp($data['creation_date']),
            $data['expiration_date'] !== null
                ? (new DateTimeImmutable())->setTimestamp($data['expiration_date'])
                : null,
            (bool) $data['is_revoked'],
        );
    }

    /**
     * @param TokenTypeEnum $type
     * @param _NewToken $data
     *
     * @throws DateTimeException
     * @return NewApiToken|NewJwtToken
     */
    public static function createNew(TokenTypeEnum $type, array $data): NewToken
    {
        if ($type === TokenTypeEnum::CMA) {
            /** @var _NewJwtToken $data */
            return new NewJwtToken(
                new TrimmedString($data['name']),
                $data['creator_id'],
                new TrimmedString($data['creator_name']),
                $data['expiration_date'],
            );
        }

        /** @var _NewApiToken $data */
        return new NewApiToken(
            $data['configuration_provider_id'],
            new TrimmedString($data['name']),
            $data['user_id'],
            $data['creator_id'],
            new TrimmedString($data['creator_name']),
            $data['expiration_date'],
        );
    }
}
