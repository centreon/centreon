import { useLocaleDateTimeFormat } from '@centreon/ui';

import { equals, gte } from 'ramda';
import { useRef } from 'react';

interface UseLastRefreshState {
  isLastRefreshMoreThanADay: boolean;
  labelRefresh: string;
}

export const useLastRefresh = (isFetching: number): UseLastRefreshState => {
  const previousIsFetchingRef = useRef<number | null>(null);
  const previousLastRefreshRef = useRef('');
  const previousLastRefreshDateRef = useRef<number>(Date.now());
  const { format } = useLocaleDateTimeFormat();

  const hasFetchStateChanged = !equals(
    isFetching,
    previousIsFetchingRef.current
  );

  if (isFetching && hasFetchStateChanged) {
    previousLastRefreshDateRef.current = Date.now();
  }

  previousIsFetchingRef.current = isFetching;

  const now = Date.now();

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
