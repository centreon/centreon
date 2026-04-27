import { Button } from '@mui/material';
import { useSetAtom } from 'jotai';
import { ReactElement, useCallback } from 'react';
import { useTranslation } from 'react-i18next';

import Modal from './Modal';
import { isModalOpenAtom } from './atoms';

import { labelCreateNewPoller } from '../../translatedLabels';

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
      <Button
        data-testid={labelCreateNewPoller}
        fullWidth
        onClick={open}
        size="small"
        variant="contained"
      >
        {t(labelCreateNewPoller)}
      </Button>

      <Modal />
    </>
  );
};

export default CloudInstallCommand;
