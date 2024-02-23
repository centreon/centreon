import { useTranslation } from 'react-i18next';
import { useAtom } from 'jotai';
import { useSearchParams } from 'react-router-dom';

import { useSnackbar } from '../..';

import { labelCannotEnterInFullscreen } from './translatedLabels';
import { isFullscreenActivatedAtom } from './atoms';

interface UseFullscreenState {
  enterInFullscreen: (element: HTMLElement | null) => void;
  exitFullscreen: () => void;
  fullscreenEnabled: boolean;
  isFullscreenActivated: boolean;
  resetVariables: () => void;
  toggleFullscreen: (element: HTMLElement | null) => void;
}

export const useFullscreen = (): UseFullscreenState => {
  const { t } = useTranslation();
  const [searchParams, setSearchParams] = useSearchParams(
    window.location.search
  );

  const { showErrorMessage } = useSnackbar();

  const [isFullscreenActivated, setIsFullscreenActivated] = useAtom(
    isFullscreenActivatedAtom
  );

  const resetVariables = (): void => {
    setIsFullscreenActivated(false);
    searchParams.delete('min');
    setSearchParams(searchParams);
  };

  const enterInFullscreen = (element: HTMLElement | null): void => {
    if (!document.fullscreenEnabled) {
      return;
    }

    if (!element) {
      showErrorMessage(t(labelCannotEnterInFullscreen));
    }

    element
      ?.requestFullscreen({ navigationUI: 'show' })
      .then(() => {
        setIsFullscreenActivated(true);
        searchParams.set('min', '1');
        setSearchParams(searchParams);
      })
      .catch(() => {
        showErrorMessage(t(labelCannotEnterInFullscreen));
        searchParams.delete('min');
        setSearchParams(searchParams);
        setIsFullscreenActivated(false);
      });
  };

  const exitFullscreen = (): void => {
    if (!document.fullscreenElement) {
      return;
    }
    document.exitFullscreen().then(resetVariables);
  };

  const toggleFullscreen = (element: HTMLElement | null): void => {
    if (isFullscreenActivated) {
      exitFullscreen();

      return;
    }

    enterInFullscreen(element);
  };

  return {
    enterInFullscreen,
    exitFullscreen,
    fullscreenEnabled: document.fullscreenEnabled,
    isFullscreenActivated,
    resetVariables,
    toggleFullscreen
  };
};
