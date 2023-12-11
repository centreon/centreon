import { useState } from 'react';

import { useSetAtom } from 'jotai';
import { isNil } from 'ramda';

import { playlistConfigInitialValuesAtom } from '../../../atoms';

interface UseActionsState {
  closeMoreActions: () => void;
  initializePlaylistConfiguration: () => void;
  isNestedRow: boolean;
  moreActionsOpen: HTMLElement | null;
  openMoreActions: (event) => void;
}

const useActions = (row): UseActionsState => {
  const { role, dashboards, description, id, isPublic, name, rotationTime } =
    row;

  const [moreActionsOpen, setMoreActionsOpen] = useState(null);

  const setPlaylistConfigInitialValues = useSetAtom(
    playlistConfigInitialValuesAtom
  );

  const initializePlaylistConfiguration = (): void => {
    setPlaylistConfigInitialValues({
      dashboards,
      description,
      id,
      isPublic,
      name,
      rotationTime
    });
  };

  const openMoreActions = (event): void => setMoreActionsOpen(event.target);
  const closeMoreActions = (): void => setMoreActionsOpen(null);

  const isNestedRow = !isNil(role);

  return {
    closeMoreActions,
    initializePlaylistConfiguration,
    isNestedRow,
    moreActionsOpen,
    openMoreActions
  };
};

export default useActions;
