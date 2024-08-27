import { useEffect } from 'react';

import { equals, includes } from 'ramda';
import { useSearchParams } from 'react-router-dom';

import { useDeepCompare } from '../useMemoComponent';

import { useFullscreen } from './useFullscreen';

export const router = {
  useSearchParams
};

export const useFullscreenListener = (): boolean => {
  const { toggleFullscreen, resetVariables, isFullscreenActivated } =
    useFullscreen();

  const toggle = (event: KeyboardEvent): void => {
    if (
      includes(document.activeElement?.tagName, ['INPUT', 'TEXTAREA']) ||
      equals(
        document.activeElement?.getAttribute('data-lexical-editor'),
        'true'
      ) ||
      equals(document.activeElement?.getAttribute('contenteditable'), 'true') ||
      !equals(event.code, 'KeyF')
    ) {
      return;
    }

    toggleFullscreen(document.querySelector('body'));
  };

  const changeFullscreen = (): void => {
    if (document.fullscreenElement) {
      return;
    }

    resetVariables();
  };

  useEffect(
    () => {
      document.addEventListener('fullscreenchange', changeFullscreen);

      return () => {
        document.removeEventListener('fullscreenchange', changeFullscreen);
      };
    },
    useDeepCompare([document.fullscreenElement])
  );

  useEffect(() => {
    window.addEventListener('keypress', toggle);

    return () => {
      window.removeEventListener('keypress', toggle);
    };
  }, [isFullscreenActivated]);

  return isFullscreenActivated;
};
