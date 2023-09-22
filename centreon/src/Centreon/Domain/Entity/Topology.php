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

namespace Centreon\Domain\Entity;

class Topology
{
    public const ENTITY_IDENTIFICATOR_COLUMN = 'topology_id';
    public const TABLE = 'topology';

    protected int $topology_id;

    protected ?string $topology_name = null;

    protected ?int $topology_parent = null;

    protected ?string $topology_page = null;

    protected ?int $topology_order = null;

    protected ?int $topology_group = null;

    protected ?string $topology_url = null;

    protected ?string $topology_url_opt = null;

    /** @var ?string enum('0','1') */
    protected ?string $topology_popup = null;

    /** @var ?string enum('0','1') */
    protected ?string $topology_modules = null;

    /** @var string enum('0','1') */
    protected ?string $topology_show = null;

    protected bool $is_deprecated = false;

    protected ?string $topology_style_class = null;

    protected ?string $topology_style_id = null;

    protected ?string $topology_OnClick = null;

    protected ?string $topology_feature_flag = null;

    /** @var string enum('0','1') */
    protected string $readonly;

    /** @var string enum('0','1') */
    protected string $is_react;

    public function getTopologyId(): ?int
    {
        return $this->topology_id;
    }

    public function setTopologyId(int $topology_id): void
    {
        $this->topology_id = $topology_id;
    }

    public function getTopologyName(): ?string
    {
        // get translated menu entry
        return _($this->topology_name);
    }

    public function setTopologyName(?string $topology_name): void
    {
        $this->topology_name = $topology_name;
    }

    public function getTopologyParent(): ?int
    {
        return $this->topology_parent;
    }

    public function setTopologyParent(?int $topology_parent): void
    {
        $this->topology_parent = $topology_parent;
    }

    public function getTopologyPage(): ?string
    {
        return $this->topology_page;
    }

    public function setTopologyPage(?string $topology_page): void
    {
        $this->topology_page = $topology_page;
    }

    public function getTopologyOrder(): ?int
    {
        return $this->topology_order;
    }

    public function setTopologyOrder(?int $topology_order): void
    {
        $this->topology_order = $topology_order;
    }

    public function getTopologyGroup(): ?int
    {
        return $this->topology_group;
    }

    public function setTopologyGroup(?int $topology_group): void
    {
        $this->topology_group = $topology_group;
    }

    public function getTopologyUrl(): ?string
    {
        return $this->topology_url;
    }

    public function setTopologyUrl(?string $topology_url): void
    {
        $this->topology_url = $topology_url;
    }

    public function getTopologyUrlOpt(): ?string
    {
        return $this->topology_url_opt;
    }

    public function setTopologyUrlOpt(?string $topology_url_opt): void
    {
        $this->topology_url_opt = $topology_url_opt;
    }

    public function getTopologyPopup(): ?string
    {
        return $this->topology_popup;
    }

    /**
     * @phpstan-param '0'|'1'|null $topology_popup
     *
     * @param string|null $topology_popup
     */
    public function setTopologyPopup(?string $topology_popup): void
    {
        $this->topology_popup = $topology_popup;
    }

    public function getTopologyModules(): ?string
    {
        return $this->topology_modules;
    }

    /**
     * @phpstan-param '0'|'1'|null $topology_modules
     *
     * @param string|null $topology_modules
     */
    public function setTopologyModules(?string $topology_modules): void
    {
        $this->topology_modules = $topology_modules;
    }

    public function getTopologyShow(): ?string
    {
        return $this->topology_show;
    }

    /**
     * @phpstan-param '0'|'1' $topology_show
     *
     * @param string $topology_show
     */
    public function setTopologyShow(string $topology_show): void
    {
        $this->topology_show = $topology_show;
    }

    public function getIsDeprecated(): bool
    {
        return $this->is_deprecated;
    }

    public function setIsDeprecated(string $isDeprecated): void
    {
        if (in_array($isDeprecated, ['0', '1'], true)) {
            throw new \InvalidArgumentException('deprecated parameter must be "0" or "1"');
        }
        $this->is_deprecated = (bool) $isDeprecated;
    }

    public function getTopologyStyleClass(): ?string
    {
        return $this->topology_style_class;
    }

    public function setTopologyStyleClass(?string $topology_style_class): void
    {
        $this->topology_style_class = $topology_style_class;
    }

    public function getTopologyStyleId(): ?string
    {
        return $this->topology_style_id;
    }

    public function setTopologyStyleId(?string $topology_style_id): void
    {
        $this->topology_style_id = $topology_style_id;
    }

    public function getReadonly(): ?string
    {
        return $this->readonly;
    }

    /**
     * @phpstan-param '0'|'1' $readonly
     *
     * @param string $readonly
     */
    public function setReadonly(string $readonly): void
    {
        $this->readonly = $readonly;
    }

    public function getIsReact(): ?string
    {
        return $this->is_react;
    }

    /**
     * @phpstan-param '0'|'1' $is_react
     *
     * @param string $is_react
     */
    public function setIsReact(string $is_react): void
    {
        $this->is_react = $is_react;
    }

    public function getTopologyOnClick(): ?string
    {
        return $this->topology_OnClick;
    }

    public function setTopologyOnClick(?string $topology_OnClick): void
    {
        $this->topology_OnClick = $topology_OnClick;
    }

    public function getTopologyFeatureFlag(): ?string
    {
        return $this->topology_feature_flag;
    }

    public function setTopologyFeatureFlag(?string $topology_feature_flag): void
    {
        $this->topology_feature_flag = $topology_feature_flag;
    }
}
