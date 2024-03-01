import { useState } from 'react';

import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import AddIcon from '@mui/icons-material/Add';
import Button from '@mui/material/Button';
import Typography from '@mui/material/Typography';

import { userAtom } from '@centreon/ui-context';

import { labelCreateNewToken } from '../translatedLabels';

import TokenCreationDialog from './TokenCreationDialog';
import { useStyles } from './tokenCreation.styles';

const TokenCreationButton = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const [isCreatingToken, setIsCreatingToken] = useState(false);

  const { isAdmin } = useAtomValue(userAtom);

  const createToken = (): void => {
    setIsCreatingToken(true);
  };

  const closeDialog = (): void => {
    setIsCreatingToken(false);
  };

  return (
    <>
      <Button
        classes={{ root: classes.root, startIcon: classes.startIcon }}
        data-testid={labelCreateNewToken}
        disabled={!isAdmin}
        size="small"
        startIcon={<AddIcon fontSize="inherit" />}
        variant="contained"
        onClick={createToken}
      >
        <Typography variant="body2"> {t(labelCreateNewToken)}</Typography>
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
