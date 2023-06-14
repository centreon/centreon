import { Method, ComponentColumnProps } from '@centreon/ui';

import DeleteButton from '../../../Actions/DeleteButton';
import {
  labelFailedToDeleteNotification,
  labelNotificationSuccessfullyDeleted
} from '../../../translatedLabels';
import { notificationtEndpoint } from '../../../EditPanel/api/endpoints';

const DeleteAction = ({ row }: ComponentColumnProps): JSX.Element => {
  const getEndpoint = (): string => notificationtEndpoint({ id: row.id });

  return (
    <DeleteButton
      fetchMethod={Method.DELETE}
      getEndpoint={getEndpoint}
      labelFailed={labelFailedToDeleteNotification}
      labelSuccess={labelNotificationSuccessfullyDeleted}
      notificationName={row.name}
    />
  );
};

export default DeleteAction;
