import { useSetAtom } from 'jotai';

import { pick } from 'ramda';
import {
  hostGroupsToDeleteAtom,
  hostGroupsToDuplicateAtom
} from '../../../atoms';

interface UseActionsState {
  openDeleteModal: () => void;
  openDuplicateModal: () => void;
}

const useActions = (row): UseActionsState => {
  const setHostGroupsToDelete = useSetAtom(hostGroupsToDeleteAtom);
  const setHostGroupsToDuplicate = useSetAtom(hostGroupsToDuplicateAtom);
  const hostGroupEntity = pick(['id', 'name'], row);

  const openDeleteModal = (): void => setHostGroupsToDelete([hostGroupEntity]);
  const openDuplicateModal = (): void =>
    setHostGroupsToDuplicate([hostGroupEntity]);

  return {
    openDeleteModal,
    openDuplicateModal
  };
};

export default useActions;
