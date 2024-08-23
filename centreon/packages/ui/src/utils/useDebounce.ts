import { useCallback, useEffect, useRef } from 'react';

interface Props {
  functionToDebounce: (...args) => void;
  memoProps?: Array<unknown>;
  wait: number;
}

export const useDebounce = ({
  functionToDebounce,
  wait,
  memoProps = []
}: Props): ((...args) => void) => {
  const timeoutRef = useRef<number | null>(null);
  const ref = useRef();

  useEffect(() => {
    ref.current = functionToDebounce;
  }, [functionToDebounce]);

  return useCallback((...args): void => {
    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current);
    }

    timeoutRef.current = setTimeout(() => {
      ref.current?.(...args);
    }, wait);
  }, memoProps);
};
