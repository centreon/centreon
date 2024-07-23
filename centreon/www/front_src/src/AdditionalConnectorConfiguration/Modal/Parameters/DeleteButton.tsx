import { ReactElement } from 'react';

import { Chip } from '@mui/material';
import DeleteOutlinedIcon from '@mui/icons-material/DeleteOutline';

import { useDeleteButtonStyles } from './useParametersStyles';

type Props = {
  onDeleteItem: () => void;
};

const DeleteIcon = (): ReactElement => {
  const { classes } = useDeleteButtonStyles();

  return <DeleteOutlinedIcon className={classes.deleteIcon} fontSize="small" />;
};

const DeleteButton = ({ onDeleteItem }: Props): ReactElement => {
  const { classes } = useDeleteButtonStyles();

  return (
    <div className={classes.deleteButtonContainer}>
      <Chip
        className={classes.deleteIconChip}
        deleteIcon={<DeleteIcon />}
        onClick={onDeleteItem}
        onDelete={onDeleteItem}
      />
    </div>
  );
};

export default DeleteButton;
