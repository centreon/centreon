import { useAtom, useAtomValue, useSetAtom } from 'jotai';

import {
  deleteNotificationAtom,
  isDeleteDialogOpenAtom,
  isPanelOpenAtom,
  selectedRowsAtom
} from '../atom';
import useDeleteRequest from '../api/useDeleteRequest';

interface UseDeleteState {
  closeDialog: () => void;
  isDialogOpen: boolean;
  isLoading: boolean;
  notificationName?: string;
  openDialog: () => void;
  submit: () => void;
}

const useDelete = (): UseDeleteState => {
  const [isDialogOpen, setIsDialogOpen] = useAtom(isDeleteDialogOpenAtom);
  const selectedRows = useAtomValue(selectedRowsAtom);
  const deleteNotification = useAtomValue(deleteNotificationAtom);
  const setIsPanelOpen = useSetAtom(isPanelOpenAtom);

  const openDialog = (): void => setIsDialogOpen(true);
  const closeDialog = (): void => setIsDialogOpen(false);
  const closePanel = (): void => setIsPanelOpen(false);

  const { submit, isLoading } = useDeleteRequest({
    closeDialog,
    closePanel,
    deleteNotification,
    selectedRows
  });

  return {
    closeDialog,
    isDialogOpen,
    isLoading,
    notificationName: deleteNotification?.name,
    openDialog,
    submit
  };
};

export default useDelete;
