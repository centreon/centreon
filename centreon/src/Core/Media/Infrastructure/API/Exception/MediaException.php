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

declare(strict_types = 1);

namespace Core\Media\Infrastructure\API\Exception;

class MediaException extends \Exception
{
    /**
     * @param string $propertyName
     *
     * @return self
     */
    public static function wrongFileType(string $propertyName): self
    {
        return new self(sprintf(_('[%s] The property does not contain the file'), $propertyName));
    }

    /**
     * @return self
     */
    public static function moreThanOneFileNotAllowed(): self
    {
        return new self(_('On media update, only one file is allowed'));
    }

    /**
     * @param string $propertyName
     *
     * @return self
     */
    public static function stringPropertyCanNotBeEmpty(string $propertyName): self
    {
        return new self(sprintf(_('[%s] Empty value found, but a string is required'), $propertyName));
    }

    /**
     * @param string $propertyName
     *
     * @return MediaException
     */
    public static function propertyNotPresent(string $propertyName): self
    {
        return new self(sprintf(_('[%s] The property %s is required'), $propertyName, $propertyName));
    }

    /**
     * @param string $filename
     *
     * @return MediaException
     */
    public static function errorUploadingFile(string $filename): self
    {
        return new self(sprintf(_('Error uploading file \'%s\''), $filename));
    }
}
