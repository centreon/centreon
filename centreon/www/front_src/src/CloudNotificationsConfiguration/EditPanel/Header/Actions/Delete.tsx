import { useAtomValue, useSetAtom } from 'jotai';
import { FormikValues, useFormikContext } from 'formik';

import { Method } from '@centreon/ui';

import DeleteButton from '../../../Actions/DeleteButton';
import { isPanelOpenAtom } from '../../../atom';
import {
  labelFailedToDeleteNotification,
  labelNotificationSuccessfullyDeleted
} from '../../../translatedLabels';
import { EditedNotificationIdAtom } from '../../atom';
import { notificationtEndpoint } from '../../api/endpoints';

const DeleteAction = (): JSX.Element => {
  const notificationId = useAtomValue(EditedNotificationIdAtom);
  const setPanelOpen = useSetAtom(isPanelOpenAtom);

  const getEndpoint = (): string =>
    notificationtEndpoint({ id: notificationId });

  const onSuccess = (): void => {
    setPanelOpen(false);
  };

  const {
    values: { name }
  } = useFormikContext<FormikValues>();

  return (
    <DeleteButton
      fetchMethod={Method.DELETE}
      getEndpoint={getEndpoint}
      labelFailed={labelFailedToDeleteNotification}
      labelSuccess={labelNotificationSuccessfullyDeleted}
      notificationName={name}
      onSuccess={onSuccess}
    />
  );
};

export default DeleteAction;
