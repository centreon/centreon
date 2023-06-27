import { equals } from 'ramda';
import { useAtomValue } from 'jotai';

import { useFetchQuery } from '@centreon/ui';

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

  const { data, isFetching } = useFetchQuery({
    decoder: notificationdecoder,
    getEndpoint: () => notificationtEndpoint({ id: editedNotificationId }),
    getQueryKey: () => ['notification', editedNotificationId],
    queryOptions: {
      enabled: equals(panelMode, PanelMode.Edit),
      suspense: false
    }
  });

  const initialValues =
    equals(panelMode, PanelMode.Edit) && data
      ? getInitialValues(data)
      : emptyInitialValues;

  const isLoading = equals(panelMode, PanelMode.Edit) ? isFetching : false;

  return {
    initialValues,
    isLoading
  };
};

export default useFormInitialValues;
