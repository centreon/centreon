import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { alpha } from '@mui/material';

import { ConfirmDialog } from '@centreon/ui';

import {
  labelCancel,
  labelDelete,
  labelDeleteNotification,
  labelDeleteNotificationWarning
} from '../translatedLabels';

import { useDelete } from '.';

const useStyles = makeStyles()((theme) => ({
  confirmButtons: {
    '&:hover': {
      background: alpha(theme.palette.error.main, 0.8)
    },
    background: theme.palette.error.main,
    color: theme.palette.common.white
  },
  paper: {
    width: theme.spacing(60)
  }
}));

const DeleteConfirmationDialog = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const { closeDialog, isDialogOpen, isLoading, submit, notificationName } =
    useDelete();

  return (
    <ConfirmDialog
      confirmDisabled={isLoading}
      dialogConfirmButtonClassName={classes.confirmButtons}
      dialogPaperClassName={classes.paper}
      labelCancel={t(labelCancel)}
      labelConfirm={t(labelDelete)}
      labelMessage={
        notificationName && `${t(labelDelete)} « ${notificationName} ».`
      }
      labelSecondMessage={t(labelDeleteNotificationWarning)}
      labelTitle={t(labelDeleteNotification)}
      open={isDialogOpen}
      submitting={isLoading}
      onCancel={closeDialog}
      onConfirm={submit}
    />
  );
};

export default DeleteConfirmationDialog;
