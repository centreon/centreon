import { useState } from 'react';

import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { Box } from '@mui/material';
import DeleteIcon from '@mui/icons-material/DeleteOutline';

import { IconButton } from '@centreon/ui';

import DeleteDialog from '../../../Listing/Dialogs/DeleteDialog';
import { labelDelete } from '../../../translatedLabels';

const useStyle = makeStyles()((theme) => ({
  icon: {
    color: theme.palette.text.secondary,
    fontSize: theme.spacing(2.75)
  }
}));

const DeleteAction = (): JSX.Element => {
  const { classes } = useStyle();

  const { t } = useTranslation();

  const [openDeleteDialog, setOpenDeleteDialog] = useState(false);

  const onDeleteActionClick = (): void => setOpenDeleteDialog(true);

  const onDeleteActionCancel = (): void => {
    setOpenDeleteDialog(false);
  };

  const onDeleteActionConfirm = (): void => {
    setOpenDeleteDialog(false);
  };

  return (
    <Box>
      <IconButton
        ariaLabel={t(labelDelete)}
        disabled={false}
        title={t(labelDelete)}
        onClick={onDeleteActionClick}
      >
        <DeleteIcon className={classes.icon} />
      </IconButton>
      <DeleteDialog
        open={openDeleteDialog}
        onCancel={onDeleteActionCancel}
        onConfirm={onDeleteActionConfirm}
      />
    </Box>
  );
};

export default DeleteAction;
