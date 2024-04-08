import { atom } from 'jotai';
import { isNil } from 'ramda';

import { TimeValue } from '../../common/timeSeries/models';
import { GraphTooltipData } from '../models';

export const eventMouseDownAtom = atom<null | MouseEvent>(null);
export const eventMouseUpAtom = atom<null | MouseEvent>(null);
export const eventMouseLeaveAtom = atom<null | MouseEvent>(null);
export const graphTooltipDataAtom = atom<GraphTooltipData | null>(null);

export const timeValueAtom = atom<TimeValue | null>(null);
export const mousePositionAtom = atom<MousePosition>(null);
export const isListingGraphOpenAtom = atom(false);
export type MousePosition = [number, number] | null;

interface Position {
  position: MousePosition;
}

export const changeMousePositionDerivedAtom = atom(
  null,
  (_, set, { position }: Position): void => {
    if (isNil(position)) {
      set(mousePositionAtom, null);

      return;
    }

    set(mousePositionAtom, position);
  }
);
