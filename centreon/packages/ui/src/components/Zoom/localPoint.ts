import type {
  FocusEvent as ReactFocusEvent,
  MouseEvent as ReactMouseEvent,
  TouchEvent as ReactTouchEvent
} from 'react';

import { Point } from '@visx/point';

type EventType =
  | MouseEvent
  | TouchEvent
  | FocusEvent
  | ReactFocusEvent
  | ReactMouseEvent
  | ReactTouchEvent;

type PointCoords = Pick<Point, 'x' | 'y'>;

const DEFAULT_POINT = { x: 0, y: 0 };

const isTouchEvent = (event?: EventType): event is TouchEvent =>
  !!event && 'changedTouches' in event;

export const isMouseEvent = (event?: EventType): event is MouseEvent =>
  !!event && 'clientX' in event;

const getXAndYFromEvent = (event?: EventType): PointCoords => {
  if (!event) return { ...DEFAULT_POINT };

  if (isTouchEvent(event)) {
    return event.changedTouches.length > 0
      ? {
          x: event.changedTouches[0].clientX,
          y: event.changedTouches[0].clientY
        }
      : { ...DEFAULT_POINT };
  }

  if (isMouseEvent(event)) {
    return {
      x: event.clientX,
      y: event.clientY
    };
  }

  return { ...DEFAULT_POINT };
};

export const localPoint = (event: EventType): PointCoords | null => {
  return getXAndYFromEvent(event);
};
