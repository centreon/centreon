import { useAtomValue, useSetAtom } from 'jotai';
import { pick } from 'ramda';

import { NamedEntity, ResourceRow, RowAction } from '../../../../models';
import { configurationAtom } from '../../../atoms';
import {
  resourcesToDeleteAtom,
  resourcesToDuplicateAtom,
  resourceToDuplicateWithNameAtom
} from '../../atoms';

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
  const setResourceToDuplicateWithName = useSetAtom(
    resourceToDuplicateWithNameAtom
  );

  const hostGroupEntity = pick(['id', 'name'], row) as NamedEntity;

  const openDeleteModal = (): void => setResourcesToDelete([hostGroupEntity]);
  const openDuplicateModal = (): void =>
    configuration?.api?.isDuplicateWithName
      ? setResourceToDuplicateWithName(hostGroupEntity)
      : setResourcesToDuplicate([hostGroupEntity]);

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
