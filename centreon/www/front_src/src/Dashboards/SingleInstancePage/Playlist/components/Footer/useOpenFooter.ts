import { useEffect, useRef, useState } from 'react';

const closeTimeout = 5_000;

interface UseOpenFooterState {
  openFooter: boolean;
}

export const useOpenFooter = (): UseOpenFooterState => {
  const [openFooter, setOpenFooter] = useState(false);
  const closeTimeRef = useRef<NodeJS.Timeout | null>(null);

  const close = (): void => setOpenFooter(false);

  const openAndStartCloseTimer = (): void => {
    clearTimeout(closeTimeRef.current);
    closeTimeRef.current = setTimeout(close, closeTimeout);
    setOpenFooter(true);
  };

  useEffect(() => {
    document
      .getElementById('page-body')
      ?.addEventListener('mousemove', openAndStartCloseTimer);

    return () => {
      close();
      document
        .getElementById('page-body')
        ?.removeEventListener('mousemove', openAndStartCloseTimer);
      if (closeTimeRef.current) {
        clearTimeout(closeTimeRef.current);
      }
    };
  }, []);

  return {
    openFooter
  };
};
