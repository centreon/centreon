<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Core\TimePeriod\Domain\Model;

class NewTimePeriod
{
    /**
     * @var Template[]
     */
    private array $templates = [];

    /**
     * @var Day[]
     */
    private array $days = [];

    /**
     * @var NewException[]
     */
    private array $exceptions = [];

    /**
     * @param string $name
     * @param string $alias
     */
    public function __construct(
        protected string $name,
        protected string $alias,
    ) {
    }

    /**
     * @param NewException $exception
     * @return void
     */
    public function addException(NewException $exception): void
    {
        $this->exceptions[] = $exception;
    }

    /**
     * @param Template $template
     * @return void
     */
    public function addTemplate(Template $template): void
    {
        $this->templates[] = $template;
    }

    /**
     * @return string
     */
    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * @return Day[]
     */
    public function getDays(): array
    {
        return $this->days;
    }

    /**
     * @return NewException[]
     */
    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return Template[]
     */
    public function getTemplates(): array
    {
        return $this->templates;
    }

    /**
     * @param Day[] $days
     */
    public function setDays(array $days): void
    {
        $this->days = $days;
    }

    /**
     * @param NewException[] $exceptions
     */
    public function setExceptions(array $exceptions): void
    {
        $this->exceptions = [];
        foreach ($exceptions as $exception) {
            $this->addException($exception);
        }
    }

    /**
     * @param Template[] $templates
     */
    public function setTemplates(array $templates): void
    {
        $this->templates = [];
        foreach ($templates as $template) {
            $this->addTemplate($template);
        }
    }
}
