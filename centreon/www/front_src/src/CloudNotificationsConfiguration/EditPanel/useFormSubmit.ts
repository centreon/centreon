import { useState } from 'react';

import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { equals } from 'ramda';
import { useAtomValue, useSetAtom } from 'jotai';

import {
  Method,
  ResponseError,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import {
  labelConfirmAddNotification,
  labelConfirmEditNotification,
  labelSuccessfulEditNotification,
  labelSuccessfulNotificationAdded
} from '../translatedLabels';
import { isPanelOpenAtom } from '../atom';

import { adaptNotifications } from './api/adapters';
import { PanelMode } from './models';
import { EditedNotificationIdAtom, panelModeAtom } from './atom';
import { notificationtEndpoint } from './api/endpoints';

interface UseFormState {
  dialogOpen: boolean;
  labelConfirm: string;
  panelMode: PanelMode;
  setDialogOpen;
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

  const [dialogOpen, setDialogOpen] = useState(false);

  const panelMode = useAtomValue(panelModeAtom);
  const editedNotificationId = useAtomValue(EditedNotificationIdAtom);
  const setPanelOpen = useSetAtom(isPanelOpenAtom);

  const labelConfirm = equals(panelMode, PanelMode.Create)
    ? labelConfirmAddNotification
    : labelConfirmEditNotification;

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () =>
      equals(panelMode, PanelMode.Create)
        ? notificationtEndpoint({})
        : notificationtEndpoint({ id: editedNotificationId }),
    method: equals(panelMode, PanelMode.Create) ? Method.POST : Method.PUT
  });

  const submit = (values, { setSubmitting }): Promise<void> => {
    const labelMessage = equals(panelMode, PanelMode.Create)
      ? labelSuccessfulNotificationAdded
      : labelSuccessfulEditNotification;

    return mutateAsync(adaptNotifications(values))
      .then((response) => {
        const { isError } = response as ResponseError;
        if (isError) {
          return;
        }
        showSuccessMessage(t(labelMessage));
        setDialogOpen(false);
        setPanelOpen(false);
        queryClient.invalidateQueries(['notificationsListing']);
        queryClient.invalidateQueries(['notifications']);
      })
      .finally(() => setSubmitting(false));
  };

  return {
    dialogOpen,
    labelConfirm,
    panelMode,
    setDialogOpen,
    submit
  };
};

export default useForm;
