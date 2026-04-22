import {
  buildListingEndpoint,
  type QueryParameter,
  useFetchQuery,
  useIntersectionObserver
} from '@centreon/ui';

import { equals, isNil, reduce } from 'ramda';
import { useEffect, useRef, useState } from 'react';

import type { Parameters } from '../api/buildListingEndpoint/models';

interface CursorListingMeta {
  limit: number;
  next_cursor: string | null;
}

interface CursorListing<T> {
  meta: CursorListingMeta;
  result: Array<T>;
}

interface UseInfiniteScrollListingWithCursor<T> {
  elementRef: (node) => void;
  elements: Array<T>;
  isLoading: boolean;
}

interface UseInfiniteScrollListingWithCursorProps<_T> {
  customQueryParameters?: Array<QueryParameter>;
  enabled?: boolean;
  endpoint: string;
  limit?: number;
  parameters?: Parameters;
  queryKeyName: string;
  suspense?: boolean;
}

export const useInfiniteScrollListingWithCursor = <T>({
  queryKeyName,
  endpoint,
  suspense = true,
  parameters,
  customQueryParameters,
  limit = 100,
  enabled = true
}: UseInfiniteScrollListingWithCursorProps<T>): UseInfiniteScrollListingWithCursor<T> => {
  const [cursorStack, setCursorStack] = useState<Array<string | null>>([null]);
  const [currentCursorIndex, setCurrentCursorIndex] = useState(0);
  const currentCursorIndexRef = useRef(currentCursorIndex);
  const [hasMore, setHasMore] = useState(false);

  const elements = useRef<Array<T>>([]);

  const serializedParameters = JSON.stringify(parameters);
  const serializedCustomQueryParameters = JSON.stringify(customQueryParameters);

  useEffect(() => {
    currentCursorIndexRef.current = currentCursorIndex;
  }, [currentCursorIndex]);

  // Reset pagination state when query inputs change to avoid sending stale cursors.
  useEffect(() => {
    setCursorStack([null]);
    setCurrentCursorIndex(0);
    setHasMore(false);
    elements.current = [];
  }, [serializedParameters, serializedCustomQueryParameters, endpoint, limit]);

  const currentCursor = cursorStack[currentCursorIndex] ?? null;

  const { data, isLoading, fetchStatus } = useFetchQuery<CursorListing<T>>({
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint: endpoint,
        customQueryParameters: [
          ...(customQueryParameters ?? []),
          { name: 'cursor', value: currentCursor ?? undefined }
        ],
        parameters: { limit, ...parameters }
      }),
    getQueryKey: () => [
      queryKeyName,
      currentCursor,
      serializedParameters,
      serializedCustomQueryParameters
    ],
    queryOptions: {
      enabled,
      refetchOnMount: false,
      refetchOnWindowFocus: false,
      suspense: suspense && currentCursorIndex === 0
    }
  });

  // Append next_cursor to the stack when response arrives.
  // Uses functional update so prev reflects the current (possibly reset) stack.
  useEffect(() => {
    const nextCursor = data?.meta?.next_cursor;
    if (nextCursor === undefined) {
      return;
    }

    setHasMore(nextCursor !== null);

    if (nextCursor !== null) {
      setCursorStack((prev) => {
        if (prev.length <= currentCursorIndexRef.current + 1) {
          return [...prev, nextCursor];
        }

        return prev;
      });
    }
  }, [data]);

  // Accumulate elements across cursor pages (reset on first page).
  useEffect(() => {
    if (isNil(data) || !equals(fetchStatus, 'idle')) {
      return;
    }
    elements.current = reduce<T, Array<T>>(
      (acc, element) => [...acc, element],
      currentCursorIndexRef.current === 0 ? [] : elements.current || [],
      data.result
    );
  }, [data, fetchStatus]);

  // Intersection observer: page = currentCursorIndex, maxPage = currentCursorIndex + 1
  // when hasMore, else maxPage = currentCursorIndex — so page < maxPage iff hasMore.
  const elementRef = useIntersectionObserver({
    action: () => {
      setCurrentCursorIndex((prev) => prev + 1);
    },
    loading: !equals(fetchStatus, 'idle'),
    maxPage: hasMore ? currentCursorIndex + 1 : currentCursorIndex,
    page: currentCursorIndex
  });

  return {
    elementRef,
    elements: elements.current || [],
    isLoading
  };
};
