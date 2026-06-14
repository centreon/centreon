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

namespace Tests\App\AiConfiguration\Infrastructure\Double;

use App\AiConfiguration\Domain\Llm\Exception\LlmException;
use App\AiConfiguration\Domain\Llm\LlmClientInterface;
use App\AiConfiguration\Domain\Llm\LlmRequest;
use App\AiConfiguration\Domain\Llm\LlmResponse;
use App\AiConfiguration\Domain\Llm\LlmUsage;

final class FakeLlmClient implements LlmClientInterface
{
    public ?LlmRequest $lastRequest = null;

    /** @var list<LlmRequest> */
    public array $requests = [];

    /** @var list<string> */
    public array $models = ['fake-model-a', 'fake-model-b'];

    /**
     * @param list<LlmResponse|LlmException> $queue consumed in order by successive calls,
     *                                              before falling back to $response/$exception
     */
    public function __construct(
        private readonly ?LlmResponse $response = null,
        private readonly ?LlmException $exception = null,
        private array $queue = [],
    ) {
    }

    public function complete(LlmRequest $request): LlmResponse
    {
        $this->lastRequest = $request;
        $this->requests[] = $request;

        if ($this->queue !== []) {
            $queued = array_shift($this->queue);
            if ($queued instanceof LlmException) {
                throw $queued;
            }

            return $queued;
        }

        if ($this->exception instanceof LlmException) {
            throw $this->exception;
        }

        return $this->response
            ?? new LlmResponse('pong', 'fake-model', new LlmUsage(10, 2));
    }

    public function stream(LlmRequest $request): \Generator
    {
        $response = $this->complete($request);

        yield $response->content;

        return $response;
    }

    public function listModels(): array
    {
        if ($this->exception instanceof LlmException) {
            throw $this->exception;
        }

        return $this->models;
    }
}
