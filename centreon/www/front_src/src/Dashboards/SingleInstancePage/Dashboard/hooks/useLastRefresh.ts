import { useLocaleDateTimeFormat } from '@centreon/ui';

import { equals } from 'ramda';
import { useEffect, useRef, useState } from 'react';

interface UseLastRefreshState {
  labelLastRefresh: string;
}

export const useLastRefresh = (isFetching: number): UseLastRefreshState => {
  const previousIsFetchingRef = useRef<number | null>(null);
  const lastRefreshDateRef = useRef<number>(Date.now());
  const [, forceRender] = useState(0);
  const { toHumanizedDuration } = useLocaleDateTimeFormat();

  const hasFetchStateChanged = !equals(
    isFetching,
    previousIsFetchingRef.current
  );

  if (isFetching && hasFetchStateChanged) {
    lastRefreshDateRef.current = Date.now();
  }

  previousIsFetchingRef.current = isFetching;

  useEffect(() => {
    const intervalId = setInterval(() => {
      forceRender((count) => count + 1);
    }, 1000);

    return () => clearInterval(intervalId);
  }, []);

  const elapsedSeconds = Math.floor(
    (Date.now() - lastRefreshDateRef.current) / 1000
  );

  return {
    labelLastRefresh: toHumanizedDuration(elapsedSeconds, 1)
  };
};
