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

namespace App\AiConfiguration\Infrastructure\Llm;

use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ReasoningModeEnum;
use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use Psr\Cache\CacheException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Sends a request with the first setting turning the model's reasoning down that
 * the provider accepts for it. A provider rejects a value the model does not
 * support (HTTP 400, or 422 from FastAPI servers) rather than ignoring it, so the
 * candidates are tried in order, and the outcome is remembered per endpoint and
 * model.
 *
 * The cache is only read and written around the requests, never locked while they
 * run: two concurrent requests may both discover the setting, which is harmless.
 */
final readonly class ReasoningNegotiator
{
    private const CACHE_KEY_PREFIX = 'ai_configuration.reasoning.';
    private const CACHE_TTL_SECONDS = 86400;

    /** Remembered when the model accepts none of the candidates. */
    private const NONE_ACCEPTED = -1;

    /** A model that may reason gets extra output tokens, so that it still has some left to answer. */
    private const SMALL_BUDGET = 256;
    private const SMALL_BUDGET_ALLOWANCE = 1024;
    private const BUDGET_ALLOWANCE = 2048;

    /** A rejected candidate is a 400 or 422 whose message names the setting. */
    private const REJECTION_STATUS_CODES = [400, 422];
    private const REJECTION_PATTERN = '/reasoning|thinking|effort/i';

    /**
     * @param string $scope what besides the model decides which candidates are accepted:
     *                      the client kind and its endpoint
     * @param non-empty-list<ReasoningCandidate> $candidates least reasoning first
     */
    public function __construct(
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
        private string $scope,
        private array $candidates,
    ) {
    }

    /**
     * The output token limit to request: the requested one, plus an allowance when
     * the model may still reason with the setting sent.
     */
    public static function maxTokensFor(int $maxTokens, ?ReasoningCandidate $candidate): int
    {
        if ($candidate instanceof ReasoningCandidate && $candidate->turnsReasoningOff) {
            return $maxTokens;
        }

        return $maxTokens + ($maxTokens <= self::SMALL_BUDGET ? self::SMALL_BUDGET_ALLOWANCE : self::BUDGET_ALLOWANCE);
    }

    /**
     * @param callable(?ReasoningCandidate): ResponseInterface $send sends the request with the
     *                                                               candidate (with no setting when
     *                                                               null) and returns its response,
     *                                                               once its status is successful
     *
     * @throws LlmRequestFailedException when the request fails, other than by a rejected candidate
     *
     * @return array{ResponseInterface, ReasoningCandidate|null} the response, and the candidate it was sent with
     */
    public function send(ModelName $model, ReasoningModeEnum $mode, callable $send): array
    {
        if ($mode === ReasoningModeEnum::Default) {
            return [$send(null), null];
        }

        $item = $this->findItem(self::CACHE_KEY_PREFIX . hash('xxh128', $this->scope . "\n" . $model->value));
        if ($item?->isHit() === true) {
            $index = $item->get();
            if ($index === self::NONE_ACCEPTED) {
                return [$send(null), null];
            }

            $candidate = is_int($index) ? ($this->candidates[$index] ?? null) : null;
            if ($candidate instanceof ReasoningCandidate) {
                try {
                    return [$send($candidate), $candidate];
                } catch (LlmRequestFailedException $exception) {
                    if (! $this->isRejection($exception, $model)) {
                        throw $exception;
                    }
                }
            }
            // the remembered candidate no longer applies (e.g. the model alias now
            // names another model): discover again, once
        }

        // a failed discovery is not remembered: its exception propagates
        [$response, $candidate, $index] = $this->discover($model, $send);
        if ($item instanceof CacheItemInterface) {
            $this->remember($item, $index);
        }

        return [$response, $candidate];
    }

    /**
     * A cache that fails is worked around: the setting is discovered again.
     */
    private function findItem(string $key): ?CacheItemInterface
    {
        try {
            return $this->cache->getItem($key);
        } catch (CacheException $exception) {
            $this->logger->warning('The reasoning setting of the model can not be read from the cache', [
                'exception' => $exception,
            ]);

            return null;
        }
    }

    private function remember(CacheItemInterface $item, int $index): void
    {
        $item->set($index);
        $item->expiresAfter(self::CACHE_TTL_SECONDS);

        try {
            $saved = $this->cache->save($item);
            $context = [];
        } catch (CacheException $exception) {
            $saved = false;
            $context = ['exception' => $exception];
        }

        if (! $saved) {
            $this->logger->warning(
                'The reasoning setting of the model can not be saved in the cache: it is discovered again next time',
                $context
            );
        }
    }

    /**
     * @param callable(?ReasoningCandidate): ResponseInterface $send
     *
     * @throws LlmRequestFailedException when the request fails, other than by a rejected candidate
     *
     * @return array{ResponseInterface, ReasoningCandidate|null, int} the response, the candidate it
     *                                                                was sent with and its index
     */
    private function discover(ModelName $model, callable $send): array
    {
        foreach ($this->candidates as $index => $candidate) {
            try {
                return [$send($candidate), $candidate, $index];
            } catch (LlmRequestFailedException $exception) {
                if (! $this->isRejection($exception, $model)) {
                    throw $exception;
                }
            }
        }

        $this->logger->warning(
            'The model accepts none of the settings that turn its reasoning down: it is sent none',
            ['model' => $model->value]
        );

        return [$send(null), null, self::NONE_ACCEPTED];
    }

    /**
     * The model name is left out of the match: it often echoes in the message, and
     * may itself name reasoning (e.g. deepseek-reasoner, a *-thinking-* model).
     */
    private function isRejection(LlmRequestFailedException $exception, ModelName $model): bool
    {
        return in_array($exception->getCode(), self::REJECTION_STATUS_CODES, true)
            && preg_match(self::REJECTION_PATTERN, str_ireplace($model->value, '', $exception->getMessage())) === 1;
    }
}
