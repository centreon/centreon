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

namespace Core\Application\Common\UseCase;

use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractPresenter implements PresenterInterface
{
    /** @var ResponseStatusInterface|null */
    private ?ResponseStatusInterface $responseStatus = null;

    /** @var array<string, mixed> */
    private array $responseHeaders = [];

    /** @var mixed */
    private mixed $presentedData = null;

    /**
     * @param PresenterFormatterInterface $presenterFormatter
     */
    public function __construct(protected PresenterFormatterInterface $presenterFormatter)
    {
    }

    /**
     * @inheritDoc
     */
    public function present(mixed $data): void
    {
        $this->presentedData = $data;
    }

    /**
     * @inheritDoc
     */
    public function getPresentedData(): mixed
    {
        return $this->presentedData;
    }

    /**
     * @inheritDoc
     */
    public function show(): Response
    {
        return ($this->responseStatus !== null)
            ? $this->presenterFormatter->format($this->responseStatus, $this->responseHeaders)
            : $this->presenterFormatter->format($this->presentedData, $this->responseHeaders);
    }

    /**
     * @inheritDoc
     */
    public function setResponseStatus(?ResponseStatusInterface $responseStatus): void
    {
        $this->responseStatus = $responseStatus;
    }

    /**
     * @inheritDoc
     */
    public function getResponseStatus(): ?ResponseStatusInterface
    {
        return $this->responseStatus;
    }

    /**
     * @inheritDoc
     */
    public function setResponseHeaders(array $responseHeaders): void
    {
        $this->responseHeaders = $responseHeaders;
    }

    /**
     * @inheritDoc
     */
    public function getResponseHeaders(): array
    {
        return $this->responseHeaders;
    }
}
