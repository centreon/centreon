import { useAtomValue, useSetAtom } from 'jotai';
import { map, pick } from 'ramda';
import { useState } from 'react';
import {
  hostGroupsToDeleteAtom,
  hostGroupsToDuplicateAtom,
  selectedRowsAtom
} from '../../atoms';

const useMassiveActions = () => {
  const [moreActionsOpen, setMoreActionsOpen] = useState(null);

  const selectedRows = useAtomValue(selectedRowsAtom);
  const setHostGroupsToDelete = useSetAtom(hostGroupsToDeleteAtom);
  const setHostGroupsToDuplicate = useSetAtom(hostGroupsToDuplicateAtom);

  const selectedRowsIds = selectedRows?.map((row) => row.id);
  const hostGroupEntities = map(pick(['id', 'name']), selectedRows);

  const openMoreActions = (event): void => setMoreActionsOpen(event.target);
  const closeMoreActions = (): void => setMoreActionsOpen(null);

  const openDeleteModal = (): void => setHostGroupsToDelete(hostGroupEntities);
  const openDuplicateModal = (): void =>
    setHostGroupsToDuplicate(hostGroupEntities);

  return {
    hostGroupEntities,
    openMoreActions,
    closeMoreActions,
    openDeleteModal,
    openDuplicateModal,
    selectedRowsIds,
    moreActionsOpen
  };
};

export default useMassiveActions;
