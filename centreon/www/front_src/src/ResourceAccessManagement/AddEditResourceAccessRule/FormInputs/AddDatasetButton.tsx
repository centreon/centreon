import { ReactElement } from 'react';

import { useTranslation } from 'react-i18next';

import { Button } from '@mui/material';
import { Add as AddIcon } from '@mui/icons-material';

import { labelAddNewDataset } from '../../translatedLabels';

import { useInputStyles } from './Inputs.styles';

const AddDatasetButton = (): ReactElement => {
  const { t } = useTranslation();
  const { classes } = useInputStyles();
  const dataTestId = 'addNewDataset';

  const click = (): void => {};

  return (
    <Button
      className={classes.addDatasetButton}
      color="primary"
      data-testid={dataTestId}
      startIcon={<AddIcon />}
      variant="contained"
      onClick={click}
    >
      {t(labelAddNewDataset)}
    </Button>
  );
};

export default AddDatasetButton;
