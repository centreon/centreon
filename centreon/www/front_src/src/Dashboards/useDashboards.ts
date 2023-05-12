import { useEffect, useState } from 'react';

import { dec, equals, gte, isNil } from 'ramda';
import { useAtomValue } from 'jotai';

import {
  buildListingEndpoint,
  useFetchQuery,
  useIntersectionObserver
} from '@centreon/ui';

import { dashboardsEndpoint } from './api/endpoints';
import { dashboardListDecoder } from './api/decoders';
import { Dashboard } from './models';
import { isDialogOpenAtom } from './atoms';

const limit = 30;

interface UseDashboardState {
  dashboards: Array<Dashboard>;
  elementRef: (node) => void;
  isLoading: boolean;
}

const useDashboards = (): UseDashboardState => {
  const [page, setPage] = useState(1);
  const [maxPage, setMaxPage] = useState(1);

  const isDialogOpen = useAtomValue(isDialogOpenAtom);

  const { data, isLoading, prefetchNextPage, fetchStatus } = useFetchQuery({
    decoder: dashboardListDecoder,
    getEndpoint: (params) =>
      buildListingEndpoint({
        baseEndpoint: dashboardsEndpoint,
        parameters: { limit, page: params?.page || page }
      }),
    getQueryKey: () => ['dashboards', page, isDialogOpen],
    isPaginated: true,
    queryOptions: {
      enabled: !isDialogOpen,
      keepPreviousData: true,
      refetchOnMount: false,
      refetchOnWindowFocus: false
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

  useEffect(() => {
    if (isNil(data)) {
      return;
    }

    const total = data.meta.total || 1;
    const newMaxPage = dec(Math.ceil(total / limit));
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
    dashboards: data?.result || [],
    elementRef,
    isLoading
  };
};

export default useDashboards;
