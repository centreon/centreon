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

use Centreon\Domain\Common\Assertion\Assertion;

class NewTimePeriod
{
    private string $name;
    private string $alias;

    /**
     * @var Template[]
     */
    private array $templates = [];

    /**
     * @var Day[]
     */
    private array $days = [];

    /**
     * @var NewExtraTimePeriod[]
     */
    private array $extraTimePeriods = [];

    /**
     * @param string $name
     * @param string $alias
     *
     * @throws \Assert\AssertionFailedException
     */
    public function __construct(
        string $name,
        string $alias,
    ) {
        Assertion::minLength($name, 1, 'NewTimePeriod::name');
        Assertion::maxLength($name, 200, 'NewTimePeriod::name');
        Assertion::minLength($alias, 1, 'NewTimePeriod::alias');
        Assertion::maxLength($alias, 200, 'NewTimePeriod::alias');
        $this->name = $name;
        $this->alias = $alias;
    }

    /**
     * @param NewExtraTimePeriod $exception
     * @return void
     */
    public function addException(NewExtraTimePeriod $exception): void
    {
        $this->extraTimePeriods[] = $exception;
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
     * @return NewExtraTimePeriod[]
     */
    public function getExtraTimePeriods(): array
    {
        return $this->extraTimePeriods;
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
     * @param NewExtraTimePeriod[] $extraTimePeriods
     */
    public function setExtraTimePeriods(array $extraTimePeriods): void
    {
        $this->extraTimePeriods = [];
        foreach ($extraTimePeriods as $exception) {
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
