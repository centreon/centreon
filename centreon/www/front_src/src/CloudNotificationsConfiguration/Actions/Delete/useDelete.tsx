import { useAtom, useSetAtom } from 'jotai';

import {
  deleteNotificationAtom,
  isDeleteDialogOpenAtom,
  isPanelOpenAtom,
  selectedRowsAtom
} from '../../atom';
import { DeleteNotificationType } from '../../models';
import useDeleteRequest from '../api/useDeleteRequest';

interface UseDeleteState {
  closeDialog: () => void;
  deleteItems: ({ id, name, type }: DeleteNotificationType) => void;
  isDialogOpen: boolean;
  isLoading: boolean;
  notificationName?: string;
  openDialog: () => void;
  submit: () => void;
}

const useDelete = (): UseDeleteState => {
  const [isDialogOpen, setIsDialogOpen] = useAtom(isDeleteDialogOpenAtom);
  const [deleteNotification, setDeleteInformations] = useAtom(
    deleteNotificationAtom
  );
  const [selectedRows, setSelectedRows] = useAtom(selectedRowsAtom);
  const setIsPanelOpen = useSetAtom(isPanelOpenAtom);

  const openDialog = (): void => setIsDialogOpen(true);
  const closeDialog = (): void => setIsDialogOpen(false);
  const closePanel = (): void => setIsPanelOpen(false);

  const deleteItems = ({ id, name, type }: DeleteNotificationType): void => {
    setDeleteInformations({
      id,
      name,
      type
    });
    setIsDialogOpen(true);
  };

  const onSettled = (): void => {
    setSelectedRows([]);
    closeDialog();
    closePanel();
  };

  const { submit, isLoading } = useDeleteRequest({
    deleteNotification,
    onSettled,
    selectedRows
  });

  return {
    closeDialog,
    deleteItems,
    isDialogOpen,
    isLoading,
    notificationName: deleteNotification?.name,
    openDialog,
    submit
  };
};

export default useDelete;
