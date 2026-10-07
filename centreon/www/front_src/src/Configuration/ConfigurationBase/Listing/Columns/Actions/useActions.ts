// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { useAtomValue, useSetAtom } from 'jotai';
import { pick } from 'ramda';

import { ResourceRow, RowAction } from '../../../../models';
import { configurationAtom } from '../../../atoms';
import { resourcesToDeleteAtom, resourcesToDuplicateAtom } from '../../atoms';

interface UseActionsState {
  openDeleteModal: () => void;
  openDuplicateModal: () => void;
  canDelete: boolean;
  canDuplicate: boolean;
  rowActions: Array<RowAction>;
}

const useActions = (row: ResourceRow): UseActionsState => {
  const configuration = useAtomValue(configurationAtom);
  const actions = configuration?.actions;

  const setResourcesToDelete = useSetAtom(resourcesToDeleteAtom);
  const setResourcesToDuplicate = useSetAtom(resourcesToDuplicateAtom);

  const hostGroupEntity = pick(['id', 'name'], row);

  const openDeleteModal = (): void => setResourcesToDelete([hostGroupEntity]);
  const openDuplicateModal = (): void =>
    setResourcesToDuplicate([hostGroupEntity]);

  const canDelete = !!actions?.delete?.(row);
  const canDuplicate = !!actions?.duplicate?.(row);

  const rowActions = (actions?.rowActions ?? []).filter(
    ({ isVisible }) => isVisible?.(row) ?? true
  );

  return {
    canDelete,
    canDuplicate,
    openDeleteModal,
    openDuplicateModal,
    rowActions
  };
};

export default useActions;
