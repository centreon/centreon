import { ReactElement } from 'react';

import { useTranslation } from 'react-i18next';

import { Button } from '@mui/material';
import { Add as AddIcon } from '@mui/icons-material';

import { labelAddNewDataset } from '../../../translatedLabels';
import { useAddDatasetButtonStyles } from '../styles/AddDatasetButton.styles';

type Props = {
  addButtonDisabled: boolean;
  onAddItem: () => void;
};

const AddDatasetButton = ({
  addButtonDisabled,
  onAddItem
}: Props): ReactElement => {
  const { t } = useTranslation();
  const { classes } = useAddDatasetButtonStyles();
  const dataTestId = 'addNewDataset';

  return (
    <Button
      aria-label={labelAddNewDataset}
      className={classes.addDatasetButton}
      color="primary"
      data-testid={dataTestId}
      disabled={addButtonDisabled}
      startIcon={<AddIcon />}
      variant="contained"
      onClick={onAddItem}
    >
      {t(labelAddNewDataset)}
    </Button>
  );
};

export default AddDatasetButton;
