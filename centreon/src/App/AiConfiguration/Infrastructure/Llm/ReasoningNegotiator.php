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
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Sends a request with the first setting turning the model's reasoning down that
 * the provider accepts for it. A provider rejects a value the model does not
 * support (HTTP 400) rather than ignoring it, so the candidates are tried in
 * order, and the outcome is remembered per endpoint and model.
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

    /** A rejected candidate is a 400 whose message names the setting. */
    private const REJECTION_PATTERN = '/reasoning|thinking|effort/i';

    /**
     * @param string $scope what besides the model decides which candidates are accepted:
     *                      the client kind and its endpoint
     * @param non-empty-list<ReasoningCandidate> $candidates least reasoning first
     */
    public function __construct(
        private CacheInterface $cache,
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

        $key = self::CACHE_KEY_PREFIX . hash('xxh128', $this->scope . "\n" . $model->value);
        // the response of the request that discovered the setting, when the cache did not know it
        $discovered = null;
        $discover = function (ItemInterface $item) use ($model, $send, &$discovered): int {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);
            [$response, $candidate, $index] = $this->discover($model, $send);
            $discovered = [$response, $candidate];

            return $index;
        };

        // a failed discovery is not remembered: the callback exception propagates
        $index = $this->cache->get($key, $discover);
        /** @var array{ResponseInterface, ReasoningCandidate|null}|null $discovered */
        if ($discovered !== null) {
            return $discovered;
        }

        if ($index === self::NONE_ACCEPTED) {
            return [$send(null), null];
        }

        $candidate = is_int($index) ? ($this->candidates[$index] ?? null) : null;
        if ($candidate instanceof ReasoningCandidate) {
            try {
                return [$send($candidate), $candidate];
            } catch (LlmRequestFailedException $exception) {
                if (! $this->isRejection($exception)) {
                    throw $exception;
                }
            }
        }

        // the remembered candidate no longer applies (e.g. the model alias now names
        // another model): discover again, once
        [$response, $candidate, $index] = $this->discover($model, $send);
        // forced: replaces the remembered candidate
        $this->cache->get($key, static function (ItemInterface $item) use ($index): int {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);

            return $index;
        }, \INF);

        return [$response, $candidate];
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
                if (! $this->isRejection($exception)) {
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

    private function isRejection(LlmRequestFailedException $exception): bool
    {
        return $exception->getCode() === 400
            && preg_match(self::REJECTION_PATTERN, $exception->getMessage()) === 1;
    }
}
