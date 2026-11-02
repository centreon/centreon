import { useAtom, useSetAtom } from 'jotai';
import { equals, isNil } from 'ramda';
import { useEffect } from 'react';
import { useSearchParams } from 'react-router';

import {
  historyExpandedLogIdsAtom,
  historyPageAtom,
  type SelectedTarget,
  selectedTargetAtom
} from './atoms';

const searchParameterName = 'object';

const parseTarget = (value: string | null): SelectedTarget | null => {
  const [type, id] = (value ?? '').split(':');

  return type && Number(id) > 0 ? { id: Number(id), type } : null;
};

interface UseSelectedTarget {
  close: () => void;
  isSelected: (target: SelectedTarget) => boolean;
  select: (target: SelectedTarget) => void;
  selectedTarget: SelectedTarget | null;
}

// The object whose history is shown in the side panel. The atom is the only
// source of truth, the URL is synced once by the page (useSyncSelectedTargetWithUrl)
export const useSelectedTarget = (): UseSelectedTarget => {
  const [selectedTarget, setSelectedTarget] = useAtom(selectedTargetAtom);
  const setHistoryPage = useSetAtom(historyPageAtom);
  const setExpandedLogIds = useSetAtom(historyExpandedLogIdsAtom);

  const select = (target: SelectedTarget): void => {
    setHistoryPage(1);
    setExpandedLogIds([]);
    setSelectedTarget(target);
  };

  const close = (): void => setSelectedTarget(null);

  const isSelected = (target: SelectedTarget): boolean =>
    equals(selectedTarget?.id, target.id) &&
    equals(selectedTarget?.type, target.type);

  return { close, isSelected, select, selectedTarget };
};

// Keeps the open object in the URL (?object=Host:12) so that the link can be
// shared, and opens it again when the page is loaded from such a link
export const useSyncSelectedTargetWithUrl = (): void => {
  const [selectedTarget, setSelectedTarget] = useAtom(selectedTargetAtom);
  const [searchParams, setSearchParams] = useSearchParams();

  useEffect(() => {
    setSelectedTarget(parseTarget(searchParams.get(searchParameterName)));

    return () => setSelectedTarget(null);
  }, []);

  useEffect(() => {
    const urlValue = searchParams.get(searchParameterName);
    const value = isNil(selectedTarget)
      ? null
      : `${selectedTarget.type}:${selectedTarget.id}`;

    if (equals(urlValue, value)) {
      return;
    }

    setSearchParams(
      (current) => {
        const next = new URLSearchParams(current);

        if (isNil(value)) {
          next.delete(searchParameterName);
        } else {
          next.set(searchParameterName, value);
        }

        return next;
      },
      { replace: true }
    );
  }, [selectedTarget]);
};
