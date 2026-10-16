import { RefObject, useLayoutEffect, useRef, useState } from 'react';

interface UseAvailableWidth {
  availableWidth: number | null;
  ref: RefObject<HTMLDivElement | null>;
}

const useAvailableWidth = (): UseAvailableWidth => {
  const ref = useRef<HTMLDivElement>(null);

  const [availableWidth, setAvailableWidth] = useState<number | null>(null);

  // Before paint, or a narrow page shows the panel full width for a frame.
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
