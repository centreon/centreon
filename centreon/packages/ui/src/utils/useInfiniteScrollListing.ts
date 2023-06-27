// TODO merge cleanup
import { useEffect, useMemo, useRef, useState } from 'react';

import { equals, gte, isNil, reduce } from 'ramda';
import { PrimitiveAtom, useAtom } from 'jotai';
import { JsonDecoder } from 'ts.data.json';

import {
  buildListingEndpoint,
  useFetchQuery,
  useIntersectionObserver
} from '@centreon/ui';

import type { Listing } from '../api/models';

const limit = 100;

interface UseInfiniteScrollListing<T> {
  elementRef: (node) => void;
  elements: Array<T>;
  isLoading: boolean;
}

interface UseInfiniteScrollListingProps<T> {
  decoder: JsonDecoder.Decoder<Listing<T>>;
  endpoint: string;
  pageAtom: PrimitiveAtom<number>;
  queryKeyName: string;
}

export const useInfiniteScrollListing = <T>({
  queryKeyName,
  endpoint,
  decoder,
  pageAtom
}: UseInfiniteScrollListingProps<T>): UseInfiniteScrollListing<T> => {
  const [maxPage, setMaxPage] = useState(1);

  const elements = useRef<Array<T> | undefined>(undefined);

  const [page, setPage] = useAtom(pageAtom);

  const { data, isLoading, prefetchNextPage, fetchStatus } = useFetchQuery<
    Listing<T>
  >({
    decoder,
    getEndpoint: (params) =>
      buildListingEndpoint({
        baseEndpoint: endpoint,
        parameters: { limit, page: params?.page || page }
      }),
    getQueryKey: () => [queryKeyName, page],
    isPaginated: true,
    queryOptions: {
      refetchOnMount: false,
      refetchOnWindowFocus: false,
      suspense: equals(page, 1)
    }
  });

  const elementRef = useIntersectionObserver({
    action: () => {
      setPage((currentPage) => currentPage + 1);
    },
    loading: !equals(fetchStatus, 'idle'),
    maxPage,
    page
  });

  elements.current = useMemo(() => {
    if (isNil(data) || !equals(fetchStatus, 'idle')) {
      return elements.current;
    }

    return reduce<T, Array<T>>(
      (acc, element) => [...acc, element],
      equals(page, 1) ? [] : elements.current || [],
      data.result
    );
  }, [page, data, fetchStatus]);

  useEffect(() => {
    if (isNil(data)) {
      return;
    }

    const total = data.meta.total || 1;
    const newMaxPage = Math.ceil(total / limit);

    setMaxPage(newMaxPage);

    if (gte(page, newMaxPage)) {
      return;
    }

    prefetchNextPage({
      getPrefetchQueryKey: (newPage) => [`dashboards`, newPage],
      page
    });
  }, [data]);

  return {
    elementRef,
    elements: elements.current || [],
    isLoading
  };
};
