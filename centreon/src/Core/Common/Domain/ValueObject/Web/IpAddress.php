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

namespace Core\Common\Domain\ValueObject\Web;

use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Domain\ValueObject\LiteralString;

/**
 * Class.
 *
 * @class   IpAddress
 */
final class IpAddress extends LiteralString
{
    /**
     * IpAddress constructor.
     *
     * @param string $ip_address
     *
     * @throws ValueObjectException
     */
    public function __construct(string $ip_address)
    {
        if (! filter_var($ip_address, FILTER_VALIDATE_IP)) {
            throw new ValueObjectException("{$ip_address} is an invalid IP Address");
        }
        parent::__construct($ip_address);
    }
}
