<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Adaptation\Database\Exception;

use Exception;
use Throwable;

/**
 * Class
 *
 * @class   DatabaseException
 * @package Adaptation\Database\Exception
 */
abstract class DatabaseException extends Exception
{
    public const ERROR_CODE_BAD_USAGE = 1;
    public const ERROR_CODE_DATABASE = 2;
    public const ERROR_CODE_DATABASE_TRANSACTION = 3;
    public const ERROR_CODE_UNBUFFERED_QUERY = 4;

    /**
     * @var array<string,mixed>
     */
    protected array $options = [];

    /**
     * DatabaseException constructor.
     *
     * @param string              $message
     * @param int                 $code
     * @param array<string,mixed> $options
     * @param Throwable|null      $previous
     */
    public function __construct(string $message, int $code, array $options = [], ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->addOption('exception_type', $this->getClassName());
        $this->addOption('exception_message', $this->getMessage());
        $this->addOption('file', $this->getFile());
        $this->addOption('line', $this->getLine());

        if (! empty($this->getTrace())) {
            if (isset($this->getTrace()[0]['class'])) {
                $this->addOption('class', $this->getTrace()[0]['class']);
            }
            if (isset($this->getTrace()[0]['function'])) {
                $this->addOption('method', $this->getTrace()[0]['function']);
            }
        }

        $this->addOption('previous', $this->getPrevious());

        $this->addOptions($options);
    }

    /**
     * @return string
     */
    protected function getClassName(): string
    {
        return static::class;
    }

    /**
     * @return array<string,mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array<string,mixed> $options
     *
     * @return void
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    public function addOption(string $key, mixed $value): void
    {
        $this->options[$key] = $value;
    }

    /**
     * @param array<string,mixed> $options
     *
     * @return void
     */
    public function addOptions(array $options): void
    {
        $this->options = array_merge($this->getOptions(), $options);
    }
}
