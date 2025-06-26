import { useAtomValue, useSetAtom } from 'jotai';

import { pick } from 'ramda';
import { configurationAtom } from '../../../atoms';
import { resourcesToDeleteAtom, resourcesToDuplicateAtom } from '../../atoms';

interface UseActionsState {
  openDeleteModal: () => void;
  openDuplicateModal: () => void;
  canDelete: boolean;
  canDuplicate: boolean;
}

const useActions = (row): UseActionsState => {
  const configuration = useAtomValue(configurationAtom);
  const actions = configuration?.actions;

  const setResourcesToDelete = useSetAtom(resourcesToDeleteAtom);
  const setResourcesToDuplicate = useSetAtom(resourcesToDuplicateAtom);

  const hostGroupEntity = pick(['id', 'name'], row);

  const openDeleteModal = (): void => setResourcesToDelete([hostGroupEntity]);
  const openDuplicateModal = (): void =>
    setResourcesToDuplicate([hostGroupEntity]);

  const canDelete = !!actions?.delete;
  const canDuplicate = !!actions?.duplicate;

  return {
    openDeleteModal,
    openDuplicateModal,
    canDelete,
    canDuplicate
  };
};

export default useActions;
