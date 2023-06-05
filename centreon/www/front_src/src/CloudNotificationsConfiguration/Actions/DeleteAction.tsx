import { useState } from 'react';

import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';
import { useAtomValue } from 'jotai';
import { isEmpty } from 'ramda';

import { Box } from '@mui/material';
import DeleteIcon from '@mui/icons-material/DeleteOutline';

import { IconButton } from '@centreon/ui';

import { selectedRowsAtom } from '../atom';
import DeleteDialog from '../Listing/Dialogs/DeleteDialog';
import { labelDelete } from '../translatedLabels';

const useStyle = makeStyles()((theme) => ({
  icon: {
    color: theme.palette.text.secondary
  }
}));

const DeleteAction = (): JSX.Element => {
  const { classes } = useStyle();

  const { t } = useTranslation();

  const [dialogOpen, setDialogOpen] = useState(false);
  const selected = useAtomValue(selectedRowsAtom);

  const onDeleteActionClick = (): void => setDialogOpen(true);

  const onDeleteActionCancel = (): void => {
    setDialogOpen(false);
  };

  const onDeleteActionConfirm = (): void => {
    setDialogOpen(false);
  };

  return (
    <Box>
      <IconButton
        ariaLabel={t(labelDelete)}
        className={classes.icon}
        disabled={isEmpty(selected)}
        title={t(labelDelete)}
        onClick={onDeleteActionClick}
      >
        <DeleteIcon />
      </IconButton>
      <DeleteDialog
        open={dialogOpen}
        onCancel={onDeleteActionCancel}
        onConfirm={onDeleteActionConfirm}
      />
    </Box>
  );
};

export default DeleteAction;
