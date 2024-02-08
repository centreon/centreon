import { useCallback, useMemo } from 'react';

import { AccessRight, ItemState, Labels } from '../models';

interface UseItemState {
  getState: () => { label: string; state: ItemState } | null;
  groupLabel: string | null;
  initials: string;
}

export const useItem = ({
  name,
  isAdded,
  isRemoved,
  isUpdated,
  list,
  isContactGroup
}: Pick<
  AccessRight,
  'name' | 'isAdded' | 'isRemoved' | 'isUpdated' | 'isContactGroup'
> &
  Pick<Labels, 'list'>): UseItemState => {
  const initials = useMemo(
    () =>
      name
        .split(' ')
        .map((n) => n.charAt(0).toUpperCase())
        .slice(0, 2)
        .join(''),
    [name]
  );

  const getState = useCallback((): {
    label: string;
    state: ItemState;
  } | null => {
    if (isRemoved) {
      return {
        label: list.removed,
        state: ItemState.removed
      };
    }
    if (isAdded) {
      return {
        label: list.added,
        state: ItemState.added
      };
    }
    if (isUpdated) {
      return {
        label: list.updated,
        state: ItemState.updated
      };
    }

    return null;
  }, [isRemoved, isUpdated, isAdded]);

  return {
    getState,
    groupLabel: isContactGroup ? list.group : null,
    initials
  };
};
