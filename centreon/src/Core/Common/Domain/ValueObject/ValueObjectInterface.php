<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Core\Common\Domain\ValueObject;

use Core\Common\Domain\Exception\ValueObjectException;

/**
 * Interface
 *
 * @class   ValueObjectInterface
 * @package Core\Common\Domain\ValueObject
 */
interface ValueObjectInterface extends \JsonSerializable, \Stringable
{
    /**
     * @param ValueObjectInterface $object
     *
     * @throws ValueObjectException
     * @return bool
     */
    public function equals(self $object): bool;
}
