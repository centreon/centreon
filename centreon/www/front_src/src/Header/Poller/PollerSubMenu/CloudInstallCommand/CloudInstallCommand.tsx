import { Button } from '@mui/material';
import { pipe } from 'ramda';
import { ReactElement, useCallback } from 'react';
import { useTranslation } from 'react-i18next';

import { useSetAtom } from 'jotai';
import { isCloudInstallCommandModalOpenAtom } from './atoms';

import { labelCreateNewPoller } from '../../translatedLabels';
import CloudInstallCommandModal from './CloudInstallCommandModal';

interface Props {
  closeSubMenu: () => void;
}

const CloudInstallCommand = ({ closeSubMenu }: Props): ReactElement => {
  const { t } = useTranslation();

  const setIsOpen = useSetAtom(isCloudInstallCommandModalOpenAtom);

  const open = useCallback(() => {
    setIsOpen(true);
  }, []);

  return (
    <>
      <Button
        data-testid={labelCreateNewPoller}
        fullWidth
        onClick={pipe(closeSubMenu, open)}
        size="small"
        variant="contained"
      >
        {t(labelCreateNewPoller)}
      </Button>

      <CloudInstallCommandModal />
    </>
  );
};

export default CloudInstallCommand;
