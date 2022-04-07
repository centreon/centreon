import * as React from 'react';

interface Props {
  functionToDebounce: (...args) => void;
  memoProps?: Array<unknown>;
  wait: number;
}

const useDebounce = ({
  functionToDebounce,
  wait,
  memoProps = [],
}: Props): ((...args) => void) => {
  const timeoutRef = React.useRef<NodeJS.Timeout | null>(null);

  return React.useCallback((...args): void => {
    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current);
    }

    timeoutRef.current = setTimeout(() => {
      functionToDebounce(...args);
    }, wait);
  }, memoProps);
};

export default useDebounce;
