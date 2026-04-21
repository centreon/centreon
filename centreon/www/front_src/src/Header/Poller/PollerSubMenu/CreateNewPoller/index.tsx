import { Button } from '@mui/material';

import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import CloudInstallCommandModal from './CloudInstallCommandModal';

import { labelCreateNewPoller } from '../../translatedLabels';
import { useCloudInstallCommand } from './CloudInstallCommandModal/useCloudInstallCommand';

interface Props {
  closeSubMenu: () => void;
}

const CreateNewPoller = ({ closeSubMenu }: Props): ReactElement => {
  const { t } = useTranslation();

  const { open } = useCloudInstallCommand();

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

      <CloudInstallCommandModal />
    </>
  );
};

export default CreateNewPoller;
