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
          <Chip
            color="secondary"
            label={t(labelBeta).toLocaleUpperCase()}
            sx={{ bgcolor: 'var(--color-secondary-dark)', fontWeight: 'bold' }}
          />
        </div>
      </div>

      <Modal />
    </>
  );
};

export default CloudInstallCommand;
