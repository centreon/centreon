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

namespace Core\Common\Domain;

enum YesNoDefault:string
{
    case No = '0';
    case Yes = '1';
    case Default = '2';

	/**
	 * @param bool|string|int|null $value
	 *
	 * @return self
	 */
	public static function fromMixed(mixed $value): self
    {
        return match ($value) {
            true, '1', 1 => self::Yes,
            false, '0', 0 => self::No,
            null, '2', 2 => self::Default,
            default => throw new \ValueError("\"{$value}\" is not a valid backing value for enum YesNoDefault")
        };
    }

    public function toInt(): int
    {
        return (int) $this->value;
    }
}