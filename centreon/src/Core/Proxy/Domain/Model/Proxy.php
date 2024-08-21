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

namespace Core\Proxy\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;

class Proxy implements \Stringable
{
    private string $protocol = 'http';

    /**
     * @param string $url
     * @param ?int $port
     * @param ?string $login
     * @param ?string $password
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private string $url,
        readonly private ?int $port = null,
        private ?string $login = null,
        private ?string $password = null
    ) {
        $this->url = trim($this->url);
        Assertion::notEmptyString($this->url, 'Proxy:url');
        $this->defineProtocolFromUrl();
        if ($this->port !== null) {
            Assertion::min($this->port, 0, 'Proxy:port');
        }
        if ($this->login !== null) {
            $this->login = trim($this->login);
            Assertion::notEmptyString($this->login, 'Proxy:login');
        }
        if ($this->password !== null) {
            $this->password = trim($this->password);
            Assertion::notEmptyString($this->password, 'Proxy:password');
        }
    }

    /**
     * **Available formats:**.
     *
     * <<procotol>>://<<user>>:<<password>>@<<url>>:<<port>>
     *
     * <<procotol>>://<<user>>:<<password>>@<<url>>
     *
     * <<procotol>>://<<url>>:<<port>>
     *
     * <<procotol>>://<<url>>
     *
     * @return string
     */
    public function __toString(): string
    {
        $url = $this->protocol . '://' . $this->url;
        if ($this->login !== null) {
            $url = $this->protocol . '://' . $this->login . ':' . $this->password . '@' . $this->url;
        }
        if ($this->port !== null) {
            $url .= ':' . $this->port;
        }

        return $url;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getLogin(): ?string
    {
        return $this->login;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getProtocol(): string
    {
        return $this->protocol;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    private function defineProtocolFromUrl(): void
    {
        if ($index = mb_strpos($this->url, '://')) {
            $this->protocol = mb_substr($this->url, 0, $index);
            $this->url = mb_substr($this->url, $index + 3);
        }
    }
}
