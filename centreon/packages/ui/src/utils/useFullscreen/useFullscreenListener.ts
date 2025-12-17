import { equals, includes } from 'ramda';
import { useCallback, useEffect } from 'react';
import { useSearchParams } from 'react-router';

import { useDeepCompare } from '../useMemoComponent';
import { useFullscreen } from './useFullscreen';

export const router = {
  useSearchParams
};

export const useFullscreenListener = (): boolean => {
  const { toggleFullscreen, resetVariables, isFullscreenActivated } =
    useFullscreen();

  const toggle = useCallback(
    (event: KeyboardEvent): void => {
      if (
        includes(document.activeElement?.tagName, ['INPUT', 'TEXTAREA']) ||
        equals(
          document.activeElement?.getAttribute('data-lexical-editor'),
          'true'
        ) ||
        equals(
          document.activeElement?.getAttribute('contenteditable'),
          'true'
        ) ||
        !equals(event.code, 'KeyF')
      ) {
        return;
      }

      toggleFullscreen(document.querySelector('body'));
    },
    [toggleFullscreen]
  );

  const changeFullscreen = useCallback((): void => {
    if (document.fullscreenElement) {
      return;
    }

    resetVariables();
  }, [resetVariables]);

  useEffect(() => {
    document.addEventListener('fullscreenchange', changeFullscreen);

    return () => {
      document.removeEventListener('fullscreenchange', changeFullscreen);
    };
  }, [...useDeepCompare([document.fullscreenElement]), changeFullscreen]);

  useEffect(() => {
    window.addEventListener('keypress', toggle);

    return () => {
      window.removeEventListener('keypress', toggle);
    };
  }, [toggle]);

  return isFullscreenActivated;
};
