import { useSetAtom } from 'jotai';

import { pick } from 'ramda';
import { resourcesToDeleteAtom, resourcesToDuplicateAtom } from '../../atoms';

interface UseActionsState {
  openDeleteModal: () => void;
  openDuplicateModal: () => void;
}

const useActions = (row): UseActionsState => {
  const setResourcesToDelete = useSetAtom(resourcesToDeleteAtom);
  const setResourcesToDuplicate = useSetAtom(resourcesToDuplicateAtom);

  const hostGroupEntity = pick(['id', 'name'], row);

  const openDeleteModal = (): void => setResourcesToDelete([hostGroupEntity]);
  const openDuplicateModal = (): void =>
    setResourcesToDuplicate([hostGroupEntity]);

  return {
    openDeleteModal,
    openDuplicateModal
  };
};

export default useActions;
