import { equals } from 'ramda';
import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { useFetchQuery } from '@centreon/ui';

import { PanelMode } from '../models';
import { notificationEndpoint } from '../api/endpoints';
import { notificationdecoder } from '../api';
import { editedNotificationIdAtom, panelModeAtom } from '../atom';

import { getEmptyInitialValues, getInitialValues } from './initialValues';

interface UseFormState {
  initialValues: object;
  isLoading: boolean;
}

const useFormInitialValues = ({
  isBamModuleInstalled
}: {
  isBamModuleInstalled: boolean;
}): UseFormState => {
  const { t } = useTranslation();
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
      ? getInitialValues({ ...data, isBamModuleInstalled, t })
      : getEmptyInitialValues({ isBamModuleInstalled, t });

  const isLoading = equals(panelMode, PanelMode.Edit) ? loading : false;

  return {
    initialValues,
    isLoading
  };
};

export default useFormInitialValues;
