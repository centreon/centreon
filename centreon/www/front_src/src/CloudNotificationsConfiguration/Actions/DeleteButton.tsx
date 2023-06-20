import { useState } from 'react';

import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { makeStyles } from 'tss-react/mui';
import {
  includes,
  isEmpty,
  last,
  length,
  propEq,
  split,
  complement
} from 'ramda';
import { useAtomValue } from 'jotai';

import { Box, alpha } from '@mui/material';
import DeleteIcon from '@mui/icons-material/DeleteOutline';

import {
  IconButton,
  ResponseError,
  useMutationQuery,
  useSnackbar,
  ConfirmDialog,
  Method
} from '@centreon/ui';

import {
  labelDelete,
  labelCancel,
  labelDeleteNotification,
  labelDeleteNotificationWarning,
  labelFailedToDeleteNotifications
} from '../translatedLabels';
import { selectedRowsAtom } from '../atom';

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
  ariaLabel?: string;
  className?: string;
  disabled?: boolean;
  fetchMethod: Method;
  getEndpoint: () => string;
  iconClassName?: string;
  labelFailed: string;
  labelSuccess: string;
  notificationName?: string;
  onSuccess?: () => void;
  payload?: { ids: Array<number | string> };
}

const DeleteButton = ({
  getEndpoint,
  onSuccess,
  labelSuccess,
  labelFailed,
  fetchMethod,
  payload,
  notificationName,
  disabled = false,
  iconClassName,
  className,
  ariaLabel
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const { showSuccessMessage, showErrorMessage, showWarningMessage } =
    useSnackbar();

  const [dialogOpen, setDialogOpen] = useState(false);
  const selectedRows = useAtomValue(selectedRowsAtom);

  const queryClient = useQueryClient();
  const { isMutating, mutateAsync } = useMutationQuery({
    defaultFailureMessage: t(labelFailed) as string,
    getEndpoint,
    method: fetchMethod
  });

  const onClick = (): void => setDialogOpen(true);
  const onCancel = (): void => setDialogOpen(false);

  const onConfirm = (): void => {
    mutateAsync(payload || {}).then((response) => {
      const {
        isError,
        statusCode,
        message,
        additionalInformation: data
      } = response as ResponseError;

      if (isError) {
        return;
      }

      if (statusCode === 207) {
        const successfullResponses = data.filter(propEq('status', 204));
        const failedResponsesIds = data
          .filter(complement(propEq('status', 204)))
          .map((item) => item.href)
          .map((item) => parseInt(last(split('/', item)) as string, 10));

        if (isEmpty(successfullResponses)) {
          showErrorMessage(t(labelFailed));
          setDialogOpen(false);

          return;
        }

        if (length(successfullResponses) < length(data)) {
          const failedResponsesName = selectedRows
            .filter((item) => includes(item.id, failedResponsesIds))
            .map((item) => item.name);

          showWarningMessage(
            `${labelFailedToDeleteNotifications}: ${failedResponsesName.join(
              ', '
            )}`
          );
          setDialogOpen(false);

          return;
        }
      }

      showSuccessMessage(message || t(labelSuccess));
      setDialogOpen(false);
      onSuccess?.();
      queryClient.invalidateQueries(['notifications']);
    });
  };

  return (
    <Box>
      <IconButton
        ariaLabel={ariaLabel}
        className={className}
        disabled={disabled}
        title={t(labelDelete) as string}
        onClick={onClick}
      >
        <DeleteIcon className={iconClassName} />
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
