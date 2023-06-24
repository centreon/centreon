import { useTranslation } from 'react-i18next';

import { Box } from '@mui/material';

import type { ComponentColumnProps } from '@centreon/ui';

import { ConfirmationDialog, DeleteButton, useDelete } from '../../Actions';
import { notificationEndpoint } from '../../EditPanel/api/endpoints';
import {
  labelDeleteNotification,
  labelFailedToDeleteNotification,
  labelNotificationSuccessfullyDeleted
} from '../../translatedLabels';

import Duplicate from './Duplicate';
import useStyles from './Actions.styles';

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const getEndpoint = (): string => notificationEndpoint({ id: row.id });

  const { onClick, dialogOpen, isMutating, onCancel, onConfirm } = useDelete({
    getEndpoint,
    labelFailed: labelFailedToDeleteNotification,
    labelSuccess: labelNotificationSuccessfullyDeleted
  });

  return (
    <Box className={classes.actions}>
      <DeleteButton
        ariaLabel={t(labelDeleteNotification) as string}
        iconClassName={classes.icon}
        onClick={onClick}
      />
      <Duplicate row={row} />
      <ConfirmationDialog
        dialogOpen={dialogOpen}
        isMutating={isMutating}
        notificationName={row.name}
        onCancel={onCancel}
        onConfirm={onConfirm}
      />
    </Box>
  );
};

export default Actions;
