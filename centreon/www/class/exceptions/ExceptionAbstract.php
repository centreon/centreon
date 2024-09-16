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

/**
 * Class ExceptionAbstract
 *
 * @class ExceptionAbstract
 */
abstract class ExceptionAbstract extends Exception
{
    public const INTERNAL_ERROR_CODE = 10;
    public const DATABASE_ERROR_CODE = 11;

    /**
     * @var array
     */
    protected array $options = [];

    /**
     * @param string $message
     * @param int $code
     * @param array $options
     * @param Throwable|null $previous
     */
    public function __construct(string $message, int $code = 0, array $options = [],  ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->addOption('exception_type', $this->getClassName());
        $this->addOption('code', $this->getCode());
        $this->addOption('message', $this->getMessage());
        $this->addOption('file', $this->getFile());
        $this->addOption('line', $this->getLine());

        if (!empty($this->getTrace())) {
            if (isset($this->getTrace()[0]['class'])) {
                $this->addOption('class', $this->getTrace()[0]['class']);
            }
            if (isset($this->getTrace()[0]['function'])) {
                $this->addOption('method', $this->getTrace()[0]['function']);
            }
        }

        $this->addOptions($options);
    }

    /**
     * @return string
     */
    protected function getClassName(): string
    {
        return $this::class;
    }

    /**
     * @return array<mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array<mixed> $options
     * @return void
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @return void
     */
    public function addOption(string $key, mixed $value): void
    {
        $this->options[$key] = $value;
    }

    /**
     * @param array<mixed> $options
     * @return void
     */
    public function addOptions(array $options): void
    {
        $this->options = array_merge($this->getOptions(), $options);
    }

    /**
     * @return string
     */
    public function toJson(): string
    {
        try {
            return json_encode($this->getOptions(), JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return "[JsonException] error during json_encode : {$e->getMessage()}";
        }
    }

}
