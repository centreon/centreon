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

namespace Core\Broker\Infrastructure\Repository;

use Core\Broker\Application\Repository\WriteBrokerRepositoryInterface;

class FileBrokerRepository implements WriteBrokerRepositoryInterface
{
    /**
     * @inheritDoc
     */
    public function create(string $filename, string $content): void
    {
        if (false === \file_put_contents($filename, $content)) {
            $error = error_get_last();
            $errorMessage = (isset($error) && $error['message'] !== '')
                ? $error['message'] : 'Error while creating file.';

            throw new \Exception($errorMessage);
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(string $filename): void
    {
        if (false === \unlink($filename)) {
            $error = error_get_last();
            $errorMessage = (isset($error) && $error['message'] !== '')
                ? $error['message'] : 'Error while deleting file.';

            throw new \Exception($errorMessage);
        }
    }
}
