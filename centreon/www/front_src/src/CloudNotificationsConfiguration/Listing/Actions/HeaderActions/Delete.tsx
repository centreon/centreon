import { useAtomValue } from 'jotai';

import { Method } from '@centreon/ui';

import DeleteButton from '../../../Actions/DeleteButton';
import { selectedRowsAtom } from '../../../atom';
import {
  labelFailedToDeleteNotifications,
  labelNotificationsSuccessfullyDeleted
} from '../../../translatedLabels';
import { notificationtEndpoint } from '../../../EditPanel/api/endpoints';

const DeleteAction = (): JSX.Element => {
  const selectedRows = useAtomValue(selectedRowsAtom);

  const getEndpoint = (): string => `${notificationtEndpoint({})}/_delete`;

  const payload = {
    ids: selectedRows?.map((notification) => notification.id)
  };

  return (
    <DeleteButton
      fetchMethod={Method.POST}
      fetchPayload={payload}
      getEndpoint={getEndpoint}
      labelFailed={labelFailedToDeleteNotifications}
      labelSuccess={labelNotificationsSuccessfullyDeleted}
    />
  );
};

export default DeleteAction;
