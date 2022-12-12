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

class Paginator
{
    const PAGER_SPAN = 5;

    public function __construct(private int $currentPageNb, private int $totalRecordsCount, private int $nbResultsPerPage)
    {
    }

    public function generatePages(): array
    {
        $pages = [];
        $lowestPageNb = $this->currentPageNb;
        $highestPageNb = $this->currentPageNb;

        if ($this->currentPageNb > 1) {
            $pages['previous'] = $this->generatePage($this->currentPageNb - 1);
        }

        for ($i = self::PAGER_SPAN; $lowestPageNb > 0 && $i > 0; $i--) {
            $lowestPageNb--;
        }

        for ($i2 = 0; $i2 < (self::PAGER_SPAN + $i); $i2++) {
            $highestPageNb++;
        }

        for ($i = $lowestPageNb; $i <= $highestPageNb; $i++) {
            $pages[$i] = $this->generatePage($i);
        }

        if ($this->nbResultsPerPage < $this->totalRecordsCount) {
            $pages['next'] = $this->generatePage($this->currentPageNb + 1);
        }

        return $pages;
    }

    private function generatePage(int $pageNb): array
    {
        return [
            'url_page' => sprintf('&num=%d&limit=%d', $pageNb, $this->nbResultsPerPage),
            'label_page' => ($pageNb + 1),
            'num' => $pageNb,
            'active' => $pageNb === $this->currentPageNb,
        ];
    }
}