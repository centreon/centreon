import { equals } from 'ramda';
import { useAtomValue } from 'jotai';

import { useFetchQuery } from '@centreon/ui';

import { emptyInitialValues, getInitialValues } from './initialValues';
import { PanelMode } from './models';
import { notificationEndpoint } from './api/endpoints';
import { notificationdecoder } from './api/decoders';
import { editedNotificationIdAtom, panelModeAtom } from './atom';

interface UseFormState {
  initialValues: object;
  isLoading: boolean;
}

const useFormInitialValues = (): UseFormState => {
  const panelMode = useAtomValue(panelModeAtom);
  const editedNotificationId = useAtomValue(editedNotificationIdAtom);

  const { data, isLoading: loading } = useFetchQuery({
    decoder: notificationdecoder,
    getEndpoint: () => notificationEndpoint({ id: editedNotificationId }),
    getQueryKey: () => ['notification', editedNotificationId],
    queryOptions: {
      cacheTime: 0,
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
