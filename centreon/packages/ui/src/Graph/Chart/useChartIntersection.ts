import { useEffect, useRef, useState } from 'react';

interface GraphIntersectionState {
  isInViewport: boolean;
}

export const useIntersection = ({ element }): GraphIntersectionState => {
  const [entry, setEntry] = useState<IntersectionObserverEntry | null>(null);

  const observer = useRef<IntersectionObserver | null>(null);

  useEffect(() => {
    if (observer.current) {
      observer.current.disconnect();
    }

    observer.current = new window.IntersectionObserver(
      ([newEntry]) => setEntry(newEntry),
      {
        threshold: 0
      }
    );

    if (element) {
      observer.current.observe(element);
    }

    return (): void => {
      observer.current?.disconnect();
    };
  }, [element]);

  return {
    isInViewport: entry?.isIntersecting ?? true
  };
};
