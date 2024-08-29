import { useAtom, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { NotificationType } from '../../Panel/models';
import {
  duplicatedNotificationAtom,
  isDuplicateDialogOpenAtom,
  isPanelOpenAtom
} from '../../atom';
import {
  labelFailedToDuplicateNotification,
  labelNotificationDuplicated
} from '../../translatedLabels';
import useDuplicateRequest from '../api/useDuplicateRequest';

interface UseDeleteState {
  closeDialog: () => void;
  duplicateItem: ({
    id,
    notification
  }: {
    id: number | null;
    notification: NotificationType;
  }) => void;
  isDialogOpen: boolean;
  openDialog: () => void;
  submit: (
    values,
    {
      setSubmitting,
      resetForm
    }: {
      resetForm;
      setSubmitting;
    }
  ) => Promise<void>;
}

const useDuplicate = (): UseDeleteState => {
  const { t } = useTranslation();
  const [isDialogOpen, setIsDialogOpen] = useAtom(isDuplicateDialogOpenAtom);
  const [notification, setNotification] = useAtom(duplicatedNotificationAtom);
  const setIsPanelOpen = useSetAtom(isPanelOpenAtom);

  const openDialog = (): void => setIsDialogOpen(true);
  const closeDialog = (): void => setIsDialogOpen(false);
  const closePanel = (): void => setIsPanelOpen(false);

  const duplicateItem = ({ id, notification: data }): void => {
    setNotification({ id, notification: data });
    setIsDialogOpen(true);
  };

  const onSettled = (): void => {
    closeDialog();
    closePanel();
  };

  const { submit } = useDuplicateRequest({
    labelFailed: t(labelFailedToDuplicateNotification),
    labelSuccess: t(labelNotificationDuplicated),
    notificationId: notification?.id,
    onSettled,
    payload: notification?.notification
  });

  return {
    closeDialog,
    duplicateItem,
    isDialogOpen,
    openDialog,
    submit
  };
};

export default useDuplicate;
