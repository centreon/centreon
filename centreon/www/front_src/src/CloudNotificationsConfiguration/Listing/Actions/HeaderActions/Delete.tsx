import { useAtomValue } from 'jotai';
import { isEmpty } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Box } from '@mui/system';

import { Method } from '@centreon/ui';

import DeleteButton from '../../../Actions/DeleteButton';
import { selectedRowsAtom } from '../../../atom';
import {
  labelFailedToDeleteNotifications,
  labelNotificationsSuccessfullyDeleted
} from '../../../translatedLabels';
import { notificationEndpoint } from '../../../EditPanel/api/endpoints';

const useStyle = makeStyles()((theme) => ({
  icon: {
    color: theme.palette.text.secondary
  }
}));

const DeleteAction = (): JSX.Element => {
  const { classes } = useStyle();
  const selectedRows = useAtomValue(selectedRowsAtom);

  const getEndpoint = (): string => `${notificationEndpoint({})}/_delete`;

  const payload = {
    ids: selectedRows?.map((notification) => notification.id)
  };

  return isEmpty(selectedRows) ? (
    <Box />
  ) : (
    <DeleteButton
      ariaLabel="delete multiple notification"
      className={classes.icon}
      fetchMethod={Method.POST}
      getEndpoint={getEndpoint}
      labelFailed={labelFailedToDeleteNotifications}
      labelSuccess={labelNotificationsSuccessfullyDeleted}
      payload={payload}
    />
  );
};

export default DeleteAction;
