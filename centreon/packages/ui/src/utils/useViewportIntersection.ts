import * as React from 'react';

interface ViewportIntersectionState {
  isInViewport: boolean;
  setElement: React.Dispatch<React.SetStateAction<HTMLElement | null>>;
}

export const useViewportIntersection = (
  options?: IntersectionObserverInit,
): ViewportIntersectionState => {
  const [entry, setEntry] = React.useState<IntersectionObserverEntry | null>(
    null,
  );
  const [element, setElement] = React.useState<HTMLElement | null>(null);

  const observer = React.useRef<IntersectionObserver | null>(null);

  React.useEffect(() => {
    if (observer.current) {
      observer.current.disconnect();
    }

    observer.current = new window.IntersectionObserver(
      ([newEntry]) => setEntry(newEntry),
      options,
    );

    if (element) {
      observer.current.observe(element);
    }

    return (): void => {
      observer.current?.disconnect();
    };
  }, [element]);

  return {
    isInViewport: entry?.isIntersecting ?? true,
    setElement,
  };
};
