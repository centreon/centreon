import { useEffect, useMemo, useRef, useState } from 'react';

import { equals, gt, isNil, reduce } from 'ramda';
import { useAtom } from 'jotai';

import {
  buildListingEndpoint,
  useFetchQuery,
  useIntersectionObserver
} from '@centreon/ui';

import { pageAtom } from './atoms';
import { dashboardsEndpoint } from './api/endpoints';
import { dashboardListDecoder } from './api/decoders';
import { Dashboard } from './models';

const limit = 4;

interface UseDashboardState {
  dashboards: Array<Dashboard>;
  elementRef: (node) => void;
  isLoading: boolean;
}

const useDashboards = (): UseDashboardState => {
  const [maxPage, setMaxPage] = useState(1);
  const dashboards = useRef<Array<Dashboard> | undefined>(undefined);

  const [page, setPage] = useAtom(pageAtom);

  const { data, isLoading, prefetchNextPage, fetchStatus } = useFetchQuery({
    decoder: dashboardListDecoder,
    getEndpoint: (params) =>
      buildListingEndpoint({
        baseEndpoint: dashboardsEndpoint,
        parameters: { limit, page: params?.page || page }
      }),
    getQueryKey: () => ['dashboards', page],
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

  dashboards.current = useMemo(() => {
    if (isNil(data) || !equals(fetchStatus, 'idle')) {
      return dashboards.current;
    }

    return reduce<Dashboard, Array<Dashboard>>(
      (acc, dashboard) => [...acc, dashboard],
      equals(page, 1) ? [] : dashboards.current || [],
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

    if (gt(page, newMaxPage)) {
      return;
    }

    prefetchNextPage({
      getPrefetchQueryKey: (newPage) => [`dashboards`, newPage],
      page
    });
  }, [data]);

  return {
    dashboards: dashboards.current || [],
    elementRef,
    isLoading
  };
};

export default useDashboards;
