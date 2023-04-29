import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { alpha } from '@mui/material';

import { ConfirmDialog } from '@centreon/ui';
import type { ComponentColumnProps } from '@centreon/ui';

import {
  labelCancel,
  labelDelete,
  labelDeleteNotification,
  labelDeleteNotificationWarning
} from '../../translatedLabels';

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

const DeleteDialog = ({
  row,
  open,
  onCancel,
  onConfirm
}: ComponentColumnProps & { open: boolean }): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <ConfirmDialog
      dialogConfirmButtonClassName={classes.confirmButtons}
      dialogPaperClassName={classes.paper}
      labelCancel={t(labelCancel)}
      labelConfirm={t(labelDelete)}
      labelMessage={`${t(labelDelete)} < ${row?.name} >`}
      labelSecondMessage={t(labelDeleteNotificationWarning)}
      labelTitle={t(labelDeleteNotification)}
      open={open}
      onCancel={onCancel}
      onConfirm={onConfirm}
    />
  );
};

export default DeleteDialog;
