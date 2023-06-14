import { useState } from 'react';

import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { makeStyles } from 'tss-react/mui';

import { Box, alpha } from '@mui/material';
import DeleteIcon from '@mui/icons-material/DeleteOutline';

import {
  IconButton,
  ResponseError,
  useMutationQuery,
  useSnackbar,
  ConfirmDialog
} from '@centreon/ui';

import {
  labelDelete,
  labelCancel,
  labelDeleteNotification,
  labelDeleteNotificationWarning
} from '../translatedLabels';

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

interface Props {
  fetchMethod?;
  fetchPayload?;
  getEndpoint?;
  labelFailed?;
  labelSuccess?;
  notificationName?;
  onSuccess?;
}

const DeleteButton = ({
  getEndpoint,
  onSuccess,
  labelSuccess,
  labelFailed,
  fetchMethod,
  fetchPayload,
  notificationName
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();
  const queryClient = useQueryClient();

  const [dialogOpen, setDialogOpen] = useState(false);
  const { isMutating, mutateAsync } = useMutationQuery({
    defaultFailureMessage: t(labelFailed) as string,
    getEndpoint,
    method: fetchMethod
  });

  const onClick = (): void => setDialogOpen(true);
  const onCancel = (): void => setDialogOpen(false);

  const onConfirm = (): void => {
    mutateAsync(fetchPayload || {}).then((response) => {
      if ((response as ResponseError).isError) {
        return;
      }
      showSuccessMessage(t(labelSuccess));
      setDialogOpen(false);
      onSuccess?.();
      queryClient.invalidateQueries(['notifications']);
    });
  };

  return (
    <Box>
      <IconButton
        ariaLabel={t(labelDelete) as string}
        disabled={false}
        title={t(labelDelete) as string}
        onClick={onClick}
      >
        <DeleteIcon />
      </IconButton>

      <ConfirmDialog
        confirmDisabled={isMutating}
        dialogConfirmButtonClassName={classes.confirmButtons}
        dialogPaperClassName={classes.paper}
        labelCancel={t(labelCancel)}
        labelConfirm={t(labelDelete)}
        labelMessage={
          notificationName && `${t(labelDelete)} « ${notificationName} ».`
        }
        labelSecondMessage={t(labelDeleteNotificationWarning)}
        labelTitle={t(labelDeleteNotification)}
        open={dialogOpen}
        submitting={isMutating}
        onCancel={onCancel}
        onConfirm={onConfirm}
      />
    </Box>
  );
};

export default DeleteButton;
