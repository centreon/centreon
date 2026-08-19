import { Button, Chip } from '@mui/material';

import { useSetAtom } from 'jotai';
import { ReactElement, useCallback } from 'react';
import { useTranslation } from 'react-i18next';

import { labelBeta, labelCreateNewPoller } from '../../translatedLabels';
import { isModalOpenAtom } from './atoms';
import Modal from './Modal';

interface Props {
  closeSubMenu: () => void;
}

const CloudInstallCommand = ({ closeSubMenu }: Props): ReactElement => {
  const { t } = useTranslation();

  const setIsOpen = useSetAtom(isModalOpenAtom);

  const open = useCallback(() => {
    closeSubMenu();
    setIsOpen(true);
  }, []);

  return (
    <>
      <div className="flex items-center w-full">
        <Button
          data-testid={labelCreateNewPoller}
          onClick={open}
          size="small"
          variant="contained"
        >
          {t(labelCreateNewPoller)}
        </Button>
        <div className="grow flex justify-center">
          {/* The purple is hardcoded on purpose. secondary.dark from the MUI
              palette is derived from secondary.main in dark mode and resolves
              to ~#571671, leaving the badge at 1.34:1 against the panel, and
              var(--color-secondary-dark) is declared twice on :root — #ac28c1
              by the Tailwind theme, #b5b5b5 by the legacy Generic theme — so
              which one wins depends on stylesheet order. */}
          <Chip
            color="secondary"
            label={t(labelBeta).toLocaleUpperCase()}
            sx={{ bgcolor: '#AC28C1', fontWeight: 'bold' }}
          />
        </div>
      </div>

      <Modal />
    </>
  );
};

export default CloudInstallCommand;
