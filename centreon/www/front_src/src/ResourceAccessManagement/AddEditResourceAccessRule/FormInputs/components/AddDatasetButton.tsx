import { ReactElement } from 'react';

import { useTranslation } from 'react-i18next';

import { Avatar, Chip, Divider } from '@mui/material';
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
    <Divider className={classes.addDatasetButtonDivider} variant="middle">
      <Chip
        aria-label={labelAddNewDataset}
        avatar={
          <Avatar className={classes.addDatasetButtonAvatar}>
            <AddIcon className={classes.addDatasetButtonIcon} />
          </Avatar>
        }
        className={classes.addDatasetButtonChip}
        data-testid={dataTestId}
        disabled={addButtonDisabled}
        label={t(labelAddNewDataset)}
        variant="outlined"
        onClick={onAddItem}
      />
    </Divider>
  );
};

// const AddDatasetButton = ({
//   addButtonDisabled,
//   onAddItem
// }: Props): ReactElement => {
//   const { t } = useTranslation();
//   const { classes } = useAddDatasetButtonStyles();
//   const dataTestId = 'addNewDataset';
//
//   return (
//     <Button
//       aria-label={labelAddNewDataset}
//       className={classes.addDatasetButton}
//       color="primary"
//       data-testid={dataTestId}
//       disabled={addButtonDisabled}
//       startIcon={<AddIcon />}
//       variant="contained"
//       onClick={onAddItem}
//     >
//       {t(labelAddNewDataset)}
//     </Button>
//   );
// };

export default AddDatasetButton;
