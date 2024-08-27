import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { useSnackbar } from '../..';

import { isFullscreenActivatedAtom } from './atoms';
import { labelCannotEnterInFullscreen } from './translatedLabels';

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

  const { showErrorMessage } = useSnackbar();

  const [isFullscreenActivated, setIsFullscreenActivated] = useAtom(
    isFullscreenActivatedAtom
  );

  const resetVariables = (): void => {
    setIsFullscreenActivated(false);
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
      })
      .catch(() => {
        showErrorMessage(t(labelCannotEnterInFullscreen));
        setIsFullscreenActivated(false);
      });
  };

  const exitFullscreen = (): void => {
    document.exitFullscreen().then(resetVariables);
  };

  const toggleFullscreen = (element: HTMLElement | null): void => {
    if (isFullscreenActivated || document.fullscreenElement) {
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
