import { MutableRefObject, useEffect, useRef, useState } from 'react';

const closeTimeout = 5_000;

interface UseOpenFooterState {
  openFooter: boolean;
}

export const useOpenFooter = (
  playlistFooterRef: MutableRefObject<HTMLDivElement | null>
): UseOpenFooterState => {
  const [openFooter, setOpenFooter] = useState(false);
  const [isMouseInFooter, setInMouseInFooter] = useState(false);
  const closeTimeRef = useRef<NodeJS.Timeout | null>(null);

  const close = (): void => setOpenFooter(false);

  const openAndStartCloseTimer = (): void => {
    if (isMouseInFooter) {
      clearTimeout(closeTimeRef.current);

      return;
    }

    clearTimeout(closeTimeRef.current);
    closeTimeRef.current = setTimeout(close, closeTimeout);
    setOpenFooter(true);
  };

  const enter = (): void => {
    clearTimeout(closeTimeRef.current);
    setInMouseInFooter(true);
  };
  const exit = (): void => {
    clearTimeout(closeTimeRef.current);
    setInMouseInFooter(false);
    closeTimeRef.current = setTimeout(close, closeTimeout);
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

  useEffect(() => {
    if (playlistFooterRef.current) {
      playlistFooterRef.current.addEventListener('mouseover', enter);
      playlistFooterRef.current.addEventListener('mouseleave', exit);
    }

    return (): void => {
      playlistFooterRef.current?.removeEventListener('mouseover', enter);
      playlistFooterRef.current?.removeEventListener('mouseleave', exit);
    };
  }, [playlistFooterRef.current]);

  return {
    openFooter
  };
};
