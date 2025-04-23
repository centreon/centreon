import { useCallback, useRef } from 'react';

interface HookParam {
  action: () => void;
  intersectionObserverOptions?: IntersectionObserverInit;
  loading: boolean;
  maxPage: number;
  page: number;
}

export const useIntersectionObserver = ({
  maxPage,
  page,
  loading,
  action,
  intersectionObserverOptions
}: HookParam): ((node) => void) => {
  const observer = useRef<IntersectionObserver | null>(null);
  const lastElementRef = useCallback(
    (node) => {
      if (observer.current) {
        observer.current.disconnect();
      }

      if (loading) {
        observer.current = null;

        return;
      }

      observer.current = new IntersectionObserver(([entry]) => {
        if (entry.isIntersecting && page < maxPage) {
          action();
        }
      }, intersectionObserverOptions);

      if (node && observer.current) {
        observer.current.observe(node);
      }
    },
    [maxPage, page, loading]
  );

  return lastElementRef;
};
