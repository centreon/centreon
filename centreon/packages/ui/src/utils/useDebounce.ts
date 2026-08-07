import { useCallback, useEffect, useRef } from 'react';

// biome-ignore lint/suspicious/noExplicitAny: needed for variadic generic
type AnyFunction = (...args: Array<any>) => void;

interface Props<T extends AnyFunction> {
  functionToDebounce: T;
  memoProps?: Array<unknown>;
  wait: number;
}

export const useDebounce = <T extends AnyFunction>({
  functionToDebounce,
  wait,
  memoProps = []
}: Props<T>): ((...args: Parameters<T>) => void) => {
  const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const ref = useRef<T | undefined>(undefined);

  useEffect(() => {
    ref.current = functionToDebounce;
  }, [functionToDebounce]);

  return useCallback(
    (...args: Parameters<T>): void => {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
      }

      timeoutRef.current = setTimeout(() => {
        ref.current?.(...args);
      }, wait);
    },
    [...memoProps, wait]
  );
};
