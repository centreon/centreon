import { ReactElement } from 'react';

import DeleteOutlinedIcon from '@mui/icons-material/DeleteOutline';
import { Chip } from '@mui/material';

import { useDeleteDatasetButtonStyles } from '../styles/DeleteDatasetButton.styles';

type Props = {
  onDeleteItem: () => void;
};

const DeleteIcon = (): ReactElement => {
  const { classes } = useDeleteDatasetButtonStyles();

  return <DeleteOutlinedIcon className={classes.deleteIcon} fontSize="small" />;
};

const DeleteDatasetButton = ({ onDeleteItem }: Props): ReactElement => {
  const { classes } = useDeleteDatasetButtonStyles();

  return (
    <div className={classes.deleteDatasetButtonContainer}>
      <Chip
        className={classes.deleteIconChip}
        deleteIcon={<DeleteIcon />}
        onClick={onDeleteItem}
        onDelete={onDeleteItem}
      />
    </div>
  );
};

export default DeleteDatasetButton;
