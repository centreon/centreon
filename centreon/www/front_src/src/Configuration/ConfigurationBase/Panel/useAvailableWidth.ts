import { RefObject, useLayoutEffect, useRef, useState } from 'react';

interface UseAvailableWidth {
  availableWidth: number | null;
  ref: RefObject<HTMLDivElement | null>;
}

// Width of the area the panel opens over, followed as the window, the menu or
// the filters panel resize it.
const useAvailableWidth = (): UseAvailableWidth => {
  const ref = useRef<HTMLDivElement>(null);

  const [availableWidth, setAvailableWidth] = useState<number | null>(null);

  // Before paint: a width measured afterwards shows the panel at its full
  // width for a frame on a page too narrow to hold it.
  useLayoutEffect(() => {
    const element = ref.current;

    if (!element) {
      return undefined;
    }

    setAvailableWidth(element.clientWidth);

    const observer = new ResizeObserver(() => {
      setAvailableWidth(element.clientWidth);
    });

    observer.observe(element);

    return () => observer.disconnect();
  }, []);

  return { availableWidth, ref };
};

export default useAvailableWidth;
