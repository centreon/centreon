import { atom } from 'jotai';
import { not, isNil } from 'ramda';

import { TimeValue } from '../../common/timeSeries/models';

export const eventMouseDownAtom = atom<null | MouseEvent>(null);
export const eventMouseUpAtom = atom<null | MouseEvent>(null);
export const eventMouseLeaveAtom = atom<null | MouseEvent>(null);

export const timeValueAtom = atom<TimeValue | null>(null);
export const mousePositionAtom = atom<MousePosition>(null);
export const isListingGraphOpenAtom = atom(false);
export type MousePosition = [number, number] | null;

interface PositionTimeValue {
  position: MousePosition;
  timeValue: TimeValue | null;
}

interface NewTimeValueInViewportState {
  isInViewport?: boolean;
  newTimeValue: TimeValue | null;
}
export const changeTimeValueDerivedAtom = atom(
  null,
  (
    _,
    set,
    { newTimeValue, isInViewport }: NewTimeValueInViewportState
  ): void => {
    if (not(isInViewport)) {
      return;
    }
    set(timeValueAtom, newTimeValue);
  }
);

export const changeMousePositionAndTimeValueDerivedAtom = atom(
  null,
  (_, set, { position, timeValue }: PositionTimeValue): void => {
    if (isNil(position) || isNil(timeValue)) {
      set(mousePositionAtom, null);
      set(timeValueAtom, null);

      return;
    }
    set(mousePositionAtom, position);

    set(timeValueAtom, timeValue);
  }
);
