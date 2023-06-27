import { equals } from 'ramda';
import { useAtomValue } from 'jotai';

import { useFetchQuery } from '@centreon/ui';

import { notificationsAtom } from '../atom';

import { emptyInitialValues, getInitialValues } from './initialValues';
import { PanelMode } from './models';
import { notificationtEndpoint } from './api/endpoints';
import { notificationdecoder } from './api/decoders';
import { EditedNotificationIdAtom, panelModeAtom } from './atom';

interface UseFormState {
  initialValues: object;
  isLoading: boolean;
}

const useFormInitialValues = (): UseFormState => {
  const panelMode = useAtomValue(panelModeAtom);
  const editedNotificationId = useAtomValue(EditedNotificationIdAtom);
  const notifications = useAtomValue(notificationsAtom);

  const editedNotification = notifications.filter(
    (item) => item.id === editedNotificationId
  );

  const { data, isLoading: loading } = useFetchQuery({
    decoder: notificationdecoder,
    getEndpoint: () => notificationtEndpoint({ id: editedNotificationId }),
    getQueryKey: () => ['notification', editedNotification],
    queryOptions: {
      enabled: equals(panelMode, PanelMode.Edit),
      suspense: false
    }
  });

  const initialValues =
    equals(panelMode, PanelMode.Edit) && data
      ? getInitialValues(data)
      : emptyInitialValues;

  const isLoading = equals(panelMode, PanelMode.Edit) ? loading : false;

  return {
    initialValues,
    isLoading
  };
};

export default useFormInitialValues;
