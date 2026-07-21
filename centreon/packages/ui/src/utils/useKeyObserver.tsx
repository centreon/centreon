import { useCallback, useEffect, useState } from 'react';

interface UseKeyObserverProps {
  isShiftKeyDown: boolean;
}

export const useKeyObserver = (): UseKeyObserverProps => {
  const [isShiftKeyDown, setIsShiftKeyDown] = useState<boolean>(false);

  const pressShift = useCallback((): void => setIsShiftKeyDown(true), []);
  const releaseShift = useCallback((): void => setIsShiftKeyDown(false), []);

  const observeKeyDown = useCallback(
    (event: KeyboardEvent): void => {
      if (event.shiftKey) {
        pressShift();
      }
    },
    [pressShift]
  );

  const observeKeyUp = useCallback((): void => {
    releaseShift();
  }, [releaseShift]);

  useEffect(() => {
    window.addEventListener('keydown', observeKeyDown);
    window.addEventListener('keyup', observeKeyUp);

    return (): void => {
      window.removeEventListener('keydown', observeKeyDown);
      window.removeEventListener('keyup', observeKeyUp);
    };
  }, [observeKeyDown, observeKeyUp]);

  return {
    isShiftKeyDown
  };
};
