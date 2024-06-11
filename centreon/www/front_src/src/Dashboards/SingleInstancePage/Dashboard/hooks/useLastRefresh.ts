import { useMemo, useRef } from 'react';

import { equals } from 'ramda';

import { useLocaleDateTimeFormat } from '@centreon/ui';

export const useLastRefresh = (isFetching: number): string => {
  const previousIsFetchingRef = useRef<number | null>(null);
  const previousLastRefresh = useRef('');
  const { format } = useLocaleDateTimeFormat();

  const hasFetchStateChanged = !equals(
    isFetching,
    previousIsFetchingRef.current
  );

  const formattedLastRefresh = useMemo(() => {
    previousIsFetchingRef.current = isFetching;

    if (!isFetching || !hasFetchStateChanged) {
      return null;
    }
    const newLastRefresh = format({
      date: new Date(),
      formatString: 'L LTS'
    });
    previousLastRefresh.current = newLastRefresh;

    return newLastRefresh;
  }, [isFetching, hasFetchStateChanged]);

  return formattedLastRefresh || previousLastRefresh.current;
};
