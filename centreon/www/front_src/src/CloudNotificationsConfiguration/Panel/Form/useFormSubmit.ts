import { useQueryClient } from '@tanstack/react-query';
import { useAtomValue, useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  Method,
  ResponseError,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { isPanelOpenAtom } from '../../atom';
import {
  labelSuccessfulEditNotification,
  labelSuccessfulNotificationAdded
} from '../../translatedLabels';
import { adaptNotification } from '../api';
import { notificationEndpoint } from '../api/endpoints';
import {
  editedNotificationIdAtom,
  htmlEmailBodyAtom,
  panelModeAtom
} from '../atom';
import { PanelMode } from '../models';

interface UseFormState {
  submit: (
    values,
    {
      setSubmitting
    }: {
      setSubmitting;
    }
  ) => Promise<void>;
}

const useForm = (): UseFormState => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();
  const queryClient = useQueryClient();

  const panelMode = useAtomValue(panelModeAtom);
  const editedNotificationId = useAtomValue(editedNotificationIdAtom);
  const htmlEmailBody = useAtomValue(htmlEmailBodyAtom);
  const setPanelOpen = useSetAtom(isPanelOpenAtom);

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () =>
      equals(panelMode, PanelMode.Create)
        ? notificationEndpoint({})
        : notificationEndpoint({ id: editedNotificationId }),
    method: equals(panelMode, PanelMode.Create) ? Method.POST : Method.PUT
  });

  const submit = (values, { setSubmitting }): Promise<void> => {
    const labelMessage = equals(panelMode, PanelMode.Create)
      ? labelSuccessfulNotificationAdded
      : labelSuccessfulEditNotification;

    const payload = adaptNotification({
      ...values,
      messages: { ...values.messages, formattedMessage: htmlEmailBody }
    });

    return mutateAsync({ payload })
      .then((response) => {
        const { isError } = response as ResponseError;
        if (isError) {
          return;
        }
        showSuccessMessage(t(labelMessage));
        setPanelOpen(false);

        queryClient.invalidateQueries({ queryKey: ['notifications'] });
      })
      .finally(() => setSubmitting(false));
  };

  return {
    submit
  };
};

export default useForm;
