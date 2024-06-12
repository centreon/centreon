import { useRef } from 'react';

import { equals, gte } from 'ramda';

import { useLocaleDateTimeFormat } from '@centreon/ui';

interface UseLastRefreshState {
  isLastRefreshMoreThanADay: boolean;
  labelRefresh: string;
}

export const useLastRefresh = (isFetching: number): UseLastRefreshState => {
  const previousIsFetchingRef = useRef<number | null>(null);
  const previousLastRefreshRef = useRef('');
  const previousLastRefreshDateRef = useRef<number>(new Date().getTime());
  const { format } = useLocaleDateTimeFormat();

  const hasFetchStateChanged = !equals(
    isFetching,
    previousIsFetchingRef.current
  );

  if (isFetching && hasFetchStateChanged) {
    previousLastRefreshDateRef.current = new Date().getTime();
  }

  previousIsFetchingRef.current = isFetching;

  const now = new Date().getTime();

  const isLastRefreshMoreThanADay = gte(
    now - previousLastRefreshDateRef.current,
    1_000 * 60 * 60 * 24
  );

  const newLastRefresh = format({
    date: new Date(previousLastRefreshDateRef.current),
    formatString: isLastRefreshMoreThanADay ? 'L LT' : 'LT'
  });

  return {
    isLastRefreshMoreThanADay,
    labelRefresh: newLastRefresh || previousLastRefreshRef.current
  };
};
