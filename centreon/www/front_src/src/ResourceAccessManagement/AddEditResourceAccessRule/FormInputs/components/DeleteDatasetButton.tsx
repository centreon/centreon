import { ReactElement } from 'react';

import { Button } from '@mui/material';
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutline';

import { useDeleteDatasetButtonStyles } from '../styles/DeleteDatasetButton.styles';

type Props = {
  deleteButtonHidden: boolean;
  onDeleteItem: () => void;
};

const DeleteDatasetButton = ({
  deleteButtonHidden,
  onDeleteItem
}: Props): ReactElement => {
  const { classes } = useDeleteDatasetButtonStyles();

  return (
    <div className={classes.deleteDatasetButtonContainer}>
      <Button
        hidden={deleteButtonHidden}
        startIcon={<DeleteOutlineIcon />}
        onClick={onDeleteItem}
      />
    </div>
  );
};

export default DeleteDatasetButton;
