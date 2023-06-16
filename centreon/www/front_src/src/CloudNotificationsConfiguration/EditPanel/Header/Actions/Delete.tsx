import { useAtomValue, useSetAtom } from 'jotai';
import { FormikValues, useFormikContext } from 'formik';
import { makeStyles } from 'tss-react/mui';

import { Method } from '@centreon/ui';

import DeleteButton from '../../../Actions/DeleteButton';
import { isPanelOpenAtom } from '../../../atom';
import {
  labelFailedToDeleteNotification,
  labelNotificationSuccessfullyDeleted
} from '../../../translatedLabels';
import { editedNotificationIdAtom } from '../../atom';
import { notificationEndpoint } from '../../api/endpoints';

const useStyle = makeStyles()((theme) => ({
  icon: {
    color: theme.palette.text.secondary,
    fontSize: theme.spacing(2.5)
  }
}));

const DeleteAction = (): JSX.Element => {
  const { classes } = useStyle();

  const notificationId = useAtomValue(editedNotificationIdAtom);
  const setPanelOpen = useSetAtom(isPanelOpenAtom);

  const getEndpoint = (): string =>
    notificationEndpoint({ id: notificationId });

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
      iconClassName={classes.icon}
      labelFailed={labelFailedToDeleteNotification}
      labelSuccess={labelNotificationSuccessfullyDeleted}
      notificationName={name}
      onSuccess={onSuccess}
    />
  );
};

export default DeleteAction;
