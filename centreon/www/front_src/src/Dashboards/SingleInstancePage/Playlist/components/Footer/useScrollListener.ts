import { MutableRefObject, useEffect } from 'react';

import { isNil } from 'ramda';

export const useScrollListener = (
  dashboardsRef: MutableRefObject<HTMLDivElement | undefined>
): void => {
  const scroll = (event: WheelEvent): void => {
    event.preventDefault();
    if (isNil(dashboardsRef.current)) {
      return;
    }
    dashboardsRef.current.scrollTo({
      left: dashboardsRef.current.scrollLeft + (event.deltaX || event.deltaY)
    });
  };
  useEffect(() => {
    if (dashboardsRef.current) {
      dashboardsRef.current.addEventListener('wheel', scroll);
    }

    return (): void => {
      dashboardsRef.current?.removeEventListener('wheel', scroll);
    };
  }, [dashboardsRef.current]);
};
