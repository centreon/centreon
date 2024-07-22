import { useState } from 'react';

import { useTranslation } from 'react-i18next';

import AddIcon from '@mui/icons-material/Add';
import Button from '@mui/material/Button';

import { labelAdd } from '../../Resources/translatedLabels';
import { labelCreateNewToken } from '../translatedLabels';

import TokenCreationDialog from './TokenCreationDialog';

const TokenCreationButton = (): JSX.Element => {
  const { t } = useTranslation();

  const [isCreatingToken, setIsCreatingToken] = useState(false);

  const createToken = (): void => {
    setIsCreatingToken(true);
  };

  const closeDialog = (): void => {
    setIsCreatingToken(false);
  };

  return (
    <>
      <Button
        data-testid={labelCreateNewToken}
        startIcon={<AddIcon />}
        variant="contained"
        onClick={createToken}
      >
        {t(labelAdd)}
      </Button>

      {isCreatingToken && (
        <TokenCreationDialog
          closeDialog={closeDialog}
          isDialogOpened={isCreatingToken}
        />
      )}
    </>
  );
};

export default TokenCreationButton;
