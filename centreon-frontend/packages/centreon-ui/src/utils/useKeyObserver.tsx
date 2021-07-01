import * as React from 'react';

interface UseKeyObserverProps {
  isShiftKeyDown: boolean;
}

const useKeyObserver = (): UseKeyObserverProps => {
  const [isShiftKeyDown, setIsShiftKeyDown] = React.useState<boolean>(false);

  const pressShift = () => setIsShiftKeyDown(true);
  const releaseShift = () => setIsShiftKeyDown(false);

  const observeKeyDown = (event: KeyboardEvent): void => {
    if (event.shiftKey) {
      pressShift();
    }
  };

  const observeKeyUp = (): void => {
    releaseShift();
  };

  React.useEffect(() => {
    window.addEventListener('keydown', observeKeyDown);
    window.addEventListener('keyup', observeKeyUp);

    return () => {
      window.removeEventListener('keydown', observeKeyDown);
      window.removeEventListener('keyup', observeKeyUp);
    };
  }, []);

  return {
    isShiftKeyDown,
  };
};

export default useKeyObserver;
