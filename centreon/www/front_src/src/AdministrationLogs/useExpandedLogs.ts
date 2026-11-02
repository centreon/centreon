import { type PrimitiveAtom, useAtom } from 'jotai';
import { equals, reject } from 'ramda';

import { expandedLogIdsAtom } from './atoms';

interface UseExpandedLogs {
  isExpanded: (id: number) => boolean;
  toggle: (id: number) => void;
}

export const useExpandedLogs = (
  atom: PrimitiveAtom<Array<number>> = expandedLogIdsAtom
): UseExpandedLogs => {
  const [expandedLogIds, setExpandedLogIds] = useAtom(atom);

  const isExpanded = (id: number): boolean => expandedLogIds.includes(id);

  const toggle = (id: number): void =>
    setExpandedLogIds((current) =>
      current.includes(id) ? reject(equals(id), current) : [...current, id]
    );

  return { isExpanded, toggle };
};
