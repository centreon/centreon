import { useAtom } from 'jotai';

import {
  deleteResourceAccessRuleAtom,
  isDeleteDialogOpenAtom
} from '../../atom';
import { DeleteResourceAccessRuleType } from '../../models';
import useDeleteRequest from '../api/useDeleteRequest';

interface UseDeleteState {
  closeDialog: () => void;
  deleteItems: ({ id, name, deleteType }: DeleteResourceAccessRuleType) => void;
  isDialogOpen: boolean;
  isLoading: boolean;
  openDialog: () => void;
  resourceAccessRuleName?: string;
  submit: () => void;
}

const useDelete = (): UseDeleteState => {
  const [isDialogOpen, setIsDialogOpen] = useAtom(isDeleteDialogOpenAtom);
  const [deleteRule, setDeleteRule] = useAtom(deleteResourceAccessRuleAtom);

  const openDialog = (): void => setIsDialogOpen(true);
  const closeDialog = (): void => setIsDialogOpen(false);

  const deleteItems = ({
    id,
    name,
    deleteType
  }: DeleteResourceAccessRuleType): void => {
    setDeleteRule({ deleteType, id, name });
    setIsDialogOpen(true);
  };

  const onSettled = (): void => closeDialog();

  const { submit, isLoading } = useDeleteRequest({ deleteRule, onSettled });

  return {
    closeDialog,
    deleteItems,
    isDialogOpen,
    isLoading,
    openDialog,
    resourceAccessRuleName: deleteRule?.name,
    submit
  };
};

export default useDelete;
