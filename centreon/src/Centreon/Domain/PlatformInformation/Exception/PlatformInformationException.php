<?php

/*
 * Copyright 2005 - 2021 Centreon (https://www.centreon.com/)
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

namespace Centreon\Domain\PlatformInformation\Exception;

/**
 * This class is designed to represent a business exception in the 'Platform information' context.
 *
 * @package Centreon\Domain\PlatformInformation
 */
class PlatformInformationException extends \Exception
{
    public const CODE_FORBIDDEN = 1;

    /**
     * @return PlatformInformationException
     */
    public static function inconsistentDataException(): self
    {
        return new self(_("Central platform's API data is not consistent. Please check the 'Remote Access' form."));
    }

    public static function noRights(): self
    {
        return new self(_("You do not have sufficent rights for this action."), self::CODE_FORBIDDEN );
    }

}
