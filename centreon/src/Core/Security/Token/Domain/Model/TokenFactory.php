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
 * @phpstan-type _PollerToken array{
 *      name: string,
 *      creator_id: int,
 *      creator_name: string,
 *      creation_date: int,
 *      expiration_date: ?int,
 *      token_type: string,
 *      is_revoked: int,
 *      token_string:string,
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
 *
 * @phpstan-type _NewPollerToken array{
 *      name: string,
 *      creator_id: int,
 *      creator_name: ?string,
 *      expiration_date: ?DateTimeInterface,
 *  }
 */
final class TokenFactory
{
    /**
     * @param TokenTypeEnum $type
     * @param _Token $data
     *
     * @return ApiToken|JwtToken|PollerToken
     */
    public static function create(
        TokenTypeEnum $type,
        array $data,
    ): Token {
        switch ($type) {
            case TokenTypeEnum::CMA:
                /** @var _JwtToken $data */
                $token = new JwtToken(
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
                break;
            case TokenTypeEnum::POLLER:
                /** @var _PollerToken $data */
                $token = new PollerToken(
                    new TrimmedString($data['name']),
                    $data['creator_id'],
                    new TrimmedString($data['creator_name']),
                    (new DateTimeImmutable())->setTimestamp($data['creation_date']),
                    $data['expiration_date'] !== null
                    ? (new DateTimeImmutable())->setTimestamp($data['expiration_date'])
                    : null,
                    (bool) $data['is_revoked'],
                    $data['token_string'],
                );
                break;
            default:
                /** @var _ApiToken $data */
                $token = new ApiToken(
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
                break;
        }

        return $token;
    }

    /**
     * @param TokenTypeEnum $type
     * @param _NewToken $data
     *
     * @return NewApiToken|NewJwtToken|NewPollerToken
     */
    public static function createNew(TokenTypeEnum $type, array $data): NewToken
    {
        switch ($type) {
            case TokenTypeEnum::CMA:
                /** @var _NewJwtToken $data */
                $token =  new NewJwtToken(
                    new TrimmedString($data['name']),
                    $data['creator_id'],
                    new TrimmedString($data['creator_name']),
                    $data['expiration_date'],
                );
                break;
            case TokenTypeEnum::POLLER:
                /** @var _NewPollerToken $data */
                $token = new NewPollerToken(
                    new TrimmedString($data['name']),
                    $data['creator_id'],
                    $data['creator_name'] ? new TrimmedString($data['creator_name']) : new TrimmedString('system'),
                    $data['expiration_date'],
                );
                break;
            default:
                /** @var _NewApiToken $data */
                $token = new NewApiToken(
                    $data['configuration_provider_id'],
                    new TrimmedString($data['name']),
                    $data['user_id'],
                    $data['creator_id'],
                    new TrimmedString($data['creator_name']),
                    $data['expiration_date'],
                );
                break;
        }

        return $token;
    }
}
