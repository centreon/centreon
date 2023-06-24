import { useAtomValue } from 'jotai';
import { isEmpty } from 'ramda';

import { Box } from '@mui/material';

import { Method } from '@centreon/ui';

import { selectedRowsAtom } from '../../atom';
import { notificationEndpoint } from '../../EditPanel/api/endpoints';
import { useDelete, DeleteButton, ConfirmationDialog } from '../../Actions';
import {
  labelFailedToDeleteSelectedNotifications,
  labelNotificationsSuccessfullyDeleted
} from '../../translatedLabels';

import Add from './Add';
import useStyle from './Header.styles';

const Header = (): JSX.Element => {
  const { classes } = useStyle();

  const selectedRows = useAtomValue(selectedRowsAtom);
  const getEndpoint = (): string => `${notificationEndpoint({})}/_delete`;

  const payload = {
    ids: selectedRows?.map((notification) => notification.id)
  };

  const { onClick, dialogOpen, isMutating, onCancel, onConfirm } = useDelete({
    fetchMethod: Method.POST,
    getEndpoint,
    labelFailed: labelFailedToDeleteSelectedNotifications,
    labelSuccess: labelNotificationsSuccessfullyDeleted,
    payload
  });

  return (
    <Box className={classes.actions}>
      <Add />
      <DeleteButton
        ariaLabel="delete multiple notifications"
        className={classes.icon}
        disabled={isEmpty(selectedRows)}
        onClick={onClick}
      />
      <ConfirmationDialog
        dialogOpen={dialogOpen}
        isMutating={isMutating}
        onCancel={onCancel}
        onConfirm={onConfirm}
      />
    </Box>
  );
};

export default Header;
