import { useAtom, useSetAtom } from 'jotai';
import { equals } from 'ramda';

import {
  historyOriginLogAtom,
  selectedLogAtom,
  selectedTargetAtom
} from './atoms';
import type { ActivityLog } from './models';

interface UseSelectedLog {
  close: () => void;
  isSelected: (id: number) => boolean;
  select: (log: ActivityLog) => void;
  selectedLog: ActivityLog | null;
}

// The log whose detail is shown in the side panel. The whole row is kept: the
// API has no endpoint to read one log, and the row already holds everything
// the panel shows but the changes
export const useSelectedLog = (): UseSelectedLog => {
  const [selectedLog, setSelectedLog] = useAtom(selectedLogAtom);
  const setSelectedTarget = useSetAtom(selectedTargetAtom);
  const setOriginLog = useSetAtom(historyOriginLogAtom);

  const select = (log: ActivityLog): void => {
    // The log detail takes the place of the history in the side panel
    setSelectedTarget(null);
    setOriginLog(null);
    setSelectedLog(log);
  };

  // Also called when leaving the page: the origin of a history opened from
  // the URL afterwards must not point to this log
  const close = (): void => {
    setOriginLog(null);
    setSelectedLog(null);
  };

  const isSelected = (id: number): boolean => equals(selectedLog?.id, id);

  return { close, isSelected, select, selectedLog };
};
