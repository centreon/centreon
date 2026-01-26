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

/**
 * Class
 *
 * @class JsonFormat
 */
abstract class JsonFormat
{
    /** @var mixed */
    protected mixed $cacheData = null;

    /** @var string|null */
    protected ?string $filePath = null;

    /**
     * @param mixed $data
     *
     * @return void
     */
    public function setContent(mixed $data): void
    {
        $this->cacheData = $data;
    }

    /**
     * Defines the path of the file where the data should be written.
     *
     * @param string $filePath
     */
    public function setFilePath(string $filePath): void
    {
        $this->filePath = $filePath;
    }

    /**
     * Writes the content of the cache only if it is not empty.
     *
     * @throws Exception
     * @return int Number of bytes written
     */
    public function flushContent(): int
    {
        if (empty($this->filePath)) {
            throw new Exception('No file path defined');
        }
        if (! empty($this->cacheData)) {
            $data = json_encode($this->cacheData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $this->cacheData = null;

            $writtenBytes = file_put_contents($this->filePath, $data);
            if ($writtenBytes === false) {
                $file = $this->retrieveLastDirectory() . DIRECTORY_SEPARATOR . pathinfo($this->filePath)['basename'];

                throw new Exception(
                    sprintf('Error while writing the \'%s\' file ', $file)
                );
            }

            return $writtenBytes;
        }

        return 0;
    }

    /**
     * Retrieve the last directory.
     * (ex: /var/log/centreon/file.log => centreon)
     *
     * @return string
     */
    private function retrieveLastDirectory(): string
    {
        if ($this->filePath === null) {
            return '';
        }
        $directories = explode('/', pathinfo($this->filePath)['dirname']);

        return array_pop($directories);
    }
}
