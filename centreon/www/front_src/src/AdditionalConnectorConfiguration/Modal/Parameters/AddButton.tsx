import { ReactElement } from 'react';

import { useTranslation } from 'react-i18next';

import { Button, Divider } from '@mui/material';
import AddCircleIcon from '@mui/icons-material/AddCircle';

import { labelAddvCenterESX } from '../../translatedLabels';

import { useAddButtonStyles } from './useParametersStyles';

type Props = {
  addButtonDisabled: boolean;
  onAddItem: () => void;
};

const AddButton = ({ addButtonDisabled, onAddItem }: Props): ReactElement => {
  const { t } = useTranslation();
  const { classes } = useAddButtonStyles();
  const dataTestId = 'addNewContainer';

  return (
    <Divider className={classes.addButtonDivider} variant="middle">
      <Button
        aria-label={labelAddvCenterESX}
        className={classes.addButton}
        data-testid={dataTestId}
        disabled={addButtonDisabled}
        onClick={onAddItem}
      >
        <AddCircleIcon />
        &nbsp;
        {t(labelAddvCenterESX)}
      </Button>
    </Divider>
  );
};

export default AddButton;
