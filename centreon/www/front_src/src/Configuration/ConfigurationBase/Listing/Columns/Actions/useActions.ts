import { useAtomValue, useSetAtom } from 'jotai';

import { isNotNil } from 'ramda';
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

  const items = {
    id: isNotNil(row?.internalListingParentId)
      ? row?.internalListingParentRow.id
      : row.id,
    name: isNotNil(row?.internalListingParentId)
      ? row?.internalListingParentRow.name
      : row.name,
    subItemId: isNotNil(row?.internalListingParentId) ? row.id : undefined,
    subItemName: isNotNil(row?.internalListingParentId) ? row.name : undefined
  };

  const openDeleteModal = (): void => setResourcesToDelete([items]);
  const openDuplicateModal = (): void => setResourcesToDuplicate([items]);

  const canDelete = !!actions?.delete(row);
  const canDuplicate = !!actions?.duplicate;

  return {
    openDeleteModal,
    openDuplicateModal,
    canDelete,
    canDuplicate
  };
};

export default useActions;
