import { ReactElement } from 'react';

import { useTranslation } from 'react-i18next';

import DeleteOutlinedIcon from '@mui/icons-material/DeleteOutline';
import { Chip } from '@mui/material';

import { Tooltip } from '@centreon/ui/components';

import { labelRemoveVCenterESX } from '../../translatedLabels';

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
  const { t } = useTranslation();

  return (
    <div className={classes.deleteButtonContainer}>
      <Tooltip label={t(labelRemoveVCenterESX)} position="bottom">
        <Chip
          className={classes.deleteIconChip}
          data-testid={labelRemoveVCenterESX}
          deleteIcon={<DeleteIcon />}
          onClick={onDeleteItem}
          onDelete={onDeleteItem}
        />
      </Tooltip>
    </div>
  );
};

export default DeleteButton;
