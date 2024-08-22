import { ReactElement } from 'react';

import { useTranslation } from 'react-i18next';

import AddCircleIcon from '@mui/icons-material/AddCircle';
import { Button, Divider } from '@mui/material';

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
    <Divider className={classes.addDatasetButtonDivider} variant="middle">
      <Button
        aria-label={labelAddNewDataset}
        className={classes.addDatasetButton}
        data-testid={dataTestId}
        disabled={addButtonDisabled}
        onClick={onAddItem}
      >
        <AddCircleIcon />
        &nbsp;
        {t(labelAddNewDataset)}
      </Button>
    </Divider>
  );
};

export default AddDatasetButton;
