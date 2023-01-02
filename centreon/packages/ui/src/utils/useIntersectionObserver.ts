import { useRef, useCallback } from 'react';

interface HookParam {
  action: () => void;
  loading: boolean;
  maxPage: number;
  page: number;
}

const useIntersectionObserver = ({
  maxPage,
  page,
  loading,
  action
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
      });

      if (node && observer.current) {
        observer.current.observe(node);
      }
    },
    [maxPage, page, loading]
  );

  return lastElementRef;
};

export default useIntersectionObserver;
