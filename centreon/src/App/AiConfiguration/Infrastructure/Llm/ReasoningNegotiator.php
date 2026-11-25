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
 * candidates are tried in order, and the outcome (an index in the candidates, so
 * per candidate list too) is remembered per endpoint and model.
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
     * the model may still reason with the setting sent. A model set to reason
     * minimally that accepts no setting gets none: it most likely does not reason.
     */
    public static function maxTokensFor(int $maxTokens, ReasoningModeEnum $mode, ?ReasoningCandidate $candidate): int
    {
        $mayReason = $candidate instanceof ReasoningCandidate
            ? ! $candidate->turnsReasoningOff
            : $mode === ReasoningModeEnum::Default;
        if (! $mayReason) {
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

        $item = $this->findItem($model);
        $rejectedIndex = null;
        if ($item?->isHit() === true) {
            $index = $item->get();
            if ($index === self::NONE_ACCEPTED) {
                return [$send(null), null];
            }

            if (is_int($index) && isset($this->candidates[$index])) {
                $candidate = $this->candidates[$index];
                try {
                    return [$send($candidate), $candidate];
                } catch (LlmRequestFailedException $exception) {
                    if (! $this->isRejection($exception, $model, $candidate)) {
                        throw $exception;
                    }
                    $this->logRejection($exception, $model, $candidate);
                    $rejectedIndex = $index;
                }
            }
            // the remembered candidate no longer applies (e.g. the model alias now
            // names another model): discover again, once, without resending it
        }

        // a failed discovery is not remembered: its exception propagates
        [$response, $candidate, $index] = $this->discover($model, $send, $rejectedIndex);
        if ($item instanceof CacheItemInterface) {
            $this->remember($item, $index, $model);
        }

        return [$response, $candidate];
    }

    /**
     * A cache that fails is worked around: the setting is discovered again.
     */
    private function findItem(ModelName $model): ?CacheItemInterface
    {
        // the remembered index only means something for this candidate list
        $labels = array_map(static fn (ReasoningCandidate $candidate): string => $candidate->label, $this->candidates);
        $key = self::CACHE_KEY_PREFIX . hash('xxh128', implode("\n", [$this->scope, $model->value, ...$labels]));

        try {
            return $this->cache->getItem($key);
        } catch (CacheException $exception) {
            $this->logger->warning('The reasoning setting of the model can not be read from the cache', [
                'model' => $model->value,
                'scope' => $this->scope,
                'exception' => $exception,
            ]);

            return null;
        }
    }

    private function remember(CacheItemInterface $item, int $index, ModelName $model): void
    {
        $item->set($index);
        $item->expiresAfter(self::CACHE_TTL_SECONDS);

        $context = ['model' => $model->value, 'scope' => $this->scope, 'index' => $index];
        try {
            $saved = $this->cache->save($item);
        } catch (CacheException $exception) {
            $saved = false;
            $context['exception'] = $exception;
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
     * @param int|null $skippedIndex a candidate already rejected, not sent again
     *
     * @throws LlmRequestFailedException when the request fails, other than by a rejected candidate
     *
     * @return array{ResponseInterface, ReasoningCandidate|null, int} the response, the candidate it
     *                                                                was sent with and its index
     */
    private function discover(ModelName $model, callable $send, ?int $skippedIndex): array
    {
        foreach ($this->candidates as $index => $candidate) {
            if ($index === $skippedIndex) {
                continue;
            }

            try {
                return [$send($candidate), $candidate, $index];
            } catch (LlmRequestFailedException $exception) {
                if (! $this->isRejection($exception, $model, $candidate)) {
                    throw $exception;
                }
                $this->logRejection($exception, $model, $candidate);
            }
        }

        $this->logger->warning(
            'The model accepts none of the settings that turn its reasoning down: it is sent none',
            ['model' => $model->value, 'scope' => $this->scope]
        );

        return [$send(null), null, self::NONE_ACCEPTED];
    }

    /**
     * The message names the setting, or the field the candidate was sent in (a server
     * that does not know the field names only it, e.g. "output_config: Extra inputs
     * are not permitted").
     *
     * The model name is left out of the match: it often echoes in the message, and
     * may itself name reasoning (e.g. deepseek-reasoner, a *-thinking-* model). Only
     * whole occurrences are, so that a short name can not hide a word of the message.
     */
    private function isRejection(
        LlmRequestFailedException $exception,
        ModelName $model,
        ReasoningCandidate $candidate,
    ): bool {
        if (! in_array($exception->getCode(), self::REJECTION_STATUS_CODES, true)) {
            return false;
        }

        $message = $exception->getMessage();
        $withoutModel = preg_replace(
            '/(?<![\w.:\/-])' . preg_quote($model->value, '/') . '(?![\w.:\/-])/i',
            '',
            $message
        ) ?? $message; // on a regular expression error, the whole message is matched

        if (preg_match(self::REJECTION_PATTERN, $withoutModel) === 1) {
            return true;
        }

        foreach (array_keys($candidate->payload) as $field) {
            if (str_contains($withoutModel, (string) $field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * An expected step: the next candidate is tried. The message is the provider's,
     * already without the API token.
     */
    private function logRejection(
        LlmRequestFailedException $exception,
        ModelName $model,
        ReasoningCandidate $candidate,
    ): void {
        $this->logger->info('The model rejects a reasoning setting: the next one is tried', [
            'model' => $model->value,
            'scope' => $this->scope,
            'setting' => $candidate->label,
            'message' => $exception->getMessage(),
        ]);
    }
}
