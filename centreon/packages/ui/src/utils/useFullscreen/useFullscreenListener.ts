import { useEffect } from 'react';

import { equals } from 'ramda';

import { getUrlQueryParameters, useDeepCompare } from '../..';

import { useFullscreen } from './useFullscreen';

export const useFullscreenListener = (): void => {
  const { toggleFullscreen, resetVariables } = useFullscreen();

  const queryParameters = getUrlQueryParameters();

  const toggle = (event: KeyboardEvent): void => {
    if (!event.altKey || !equals(event.code, 'KeyF')) {
      return;
    }

    toggleFullscreen(document.body);
  };

  const changeFullscreen = (): void => {
    if (document.fullscreenElement) {
      return;
    }

    resetVariables();
  };

  useEffect(
    () => {
      window.addEventListener('keypress', toggle);
      document.addEventListener('fullscreenchange', changeFullscreen);

      return () => {
        window.removeEventListener('keypress', toggle);
        document.removeEventListener('fullscreenchange', changeFullscreen);
      };
    },
    useDeepCompare([queryParameters])
  );

  useEffect(() => {
    if (!queryParameters.min) {
      return;
    }

    resetVariables();
  }, []);
};
