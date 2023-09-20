import { useEffect, useMemo, useRef } from 'react';

import debounce from 'lodash/debounce';

const useDebounce = (callback) => {
  const ref = useRef();

  useEffect(() => {
    ref.current = callback;
  }, [callback]);

  const debouncedCallback = useMemo(() => {
    const func = () => {
      ref.current?.();
    };

    return debounce(func, 300);
  }, []);

  return debouncedCallback;
};
export default useDebounce;
