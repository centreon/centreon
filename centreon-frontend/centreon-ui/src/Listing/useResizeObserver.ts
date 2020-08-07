import * as React from 'react';

import ResizeObserver from 'resize-observer-polyfill';

interface Props<TRef> {
  ref: React.RefObject<TRef | undefined>;
  onResize: ResizeObserverCallback;
}

const useResizeObserver = <TRef extends Element>({
  ref,
  onResize,
}: Props<TRef>): void => {
  React.useEffect(() => {
    const ro = new ResizeObserver(onResize);

    const element = ref?.current as Element;

    ro.observe(element);

    return () => {
      ro.unobserve(element);
    };
  }, []);
};

export default useResizeObserver;
