import { ReactElement } from 'react';

import { Chip, Divider } from '@mui/material';
import DeleteOutlinedIcon from '@mui/icons-material/DeleteOutline';

import { useDeleteDatasetButtonStyles } from '../styles/DeleteDatasetButton.styles';

type Props = {
  deleteButtonHidden: boolean;
  displayDivider: boolean;
  onDeleteItem: () => void;
};

const DeleteIcon = (): ReactElement => {
  const { classes } = useDeleteDatasetButtonStyles();

  return <DeleteOutlinedIcon className={classes.deleteIcon} fontSize="small" />;
};

const DeleteDatasetButton = ({
  deleteButtonHidden,
  displayDivider,
  onDeleteItem
}: Props): ReactElement => {
  const { classes } = useDeleteDatasetButtonStyles();

  return (
    <div
      className={classes.deleteDatasetButtonContainer}
      hidden={deleteButtonHidden}
    >
      <Divider flexItem={!displayDivider} orientation="vertical">
        <Chip
          deleteIcon={<DeleteIcon />}
          onClick={onDeleteItem}
          onDelete={onDeleteItem}
        />
      </Divider>
    </div>
  );
};

export default DeleteDatasetButton;
