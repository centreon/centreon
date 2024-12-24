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

/**
 * @implements IteratorAggregate<int, int>
 */
class Paginator implements IteratorAggregate
{
    /**
     * Maximum number of pages displayed after / before the current page.
     */
    public const PAGER_SPAN = 5;

    /** @var positive-int */
    public readonly int $currentPageNb;

    /** @var positive-int */
    public readonly int $nbResultsPerPage;

    /** @var int<0, max> */
    public readonly int $totalRecordsCount;

    /** @var positive-int */
    public readonly int $totalPagesCount;

    public function __construct(int $currentPageNb, int $nbResultsPerPage, int $totalRecordsCount = 0)
    {
        $this->currentPageNb = max(1, $currentPageNb);
        $this->nbResultsPerPage = max(1, $nbResultsPerPage);
        $this->totalRecordsCount = max(0, $totalRecordsCount);
        $this->totalPagesCount = max(1, (int) ceil($totalRecordsCount / $nbResultsPerPage));
    }

    public function withTotalRecordCount(int $count): self
    {
        return new self($this->currentPageNb, $this->nbResultsPerPage, $count);
    }

    public function getOffset(): int
    {
        return $this->nbResultsPerPage * ($this->currentPageNb - 1);
    }

    public function getOffsetMaximum(): int
    {
        return $this->isOutOfUpperBound()
            ? $this->nbResultsPerPage * ($this->totalPagesCount - 1)
            : $this->getOffset();
    }

    public function isOutOfUpperBound(): bool
    {
        return $this->currentPageNb > $this->totalPagesCount;
    }

    public function isActive(int $pageNb): bool
    {
        return $pageNb === $this->currentPageNb
            || ($pageNb === $this->totalPagesCount && $this->isOutOfUpperBound());
    }

    public function getUrl(int $pageNb): string
    {
        return sprintf('&num=%d&limit=%d', $pageNb, $this->nbResultsPerPage);
    }

    public function getPageNumberPrevious(): ?int
    {
        return $this->currentPageNb > 1 ? $this->currentPageNb - 1 : null;
    }

    public function getPageNumberNext(): ?int
    {
        return $this->currentPageNb < $this->totalPagesCount ? $this->currentPageNb + 1 : null;
    }

    /**
     * @return Generator<int, int>
     */
    public function getIterator(): Generator
    {
        $currentPage = min($this->currentPageNb, $this->totalPagesCount);
        $lowestPageNb = max(1, $currentPage - self::PAGER_SPAN);
        $highestPageNb = min($this->totalPagesCount, $currentPage + self::PAGER_SPAN);

        yield from range($lowestPageNb, $highestPageNb);
    }
}