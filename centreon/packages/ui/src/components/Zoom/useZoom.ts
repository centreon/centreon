import type { Point, ProvidedZoom, Translate } from '@visx/zoom/lib/types';
import { equals } from 'ramda';
import type React from 'react';
import { useCallback, useState } from 'react';

import { localPoint } from './localPoint';
import type { ZoomState } from './models';

const isLeftMouseButtonClicked = (
  e: React.MouseEvent<SVGSVGElement>
): boolean => equals(e.buttons, 1);

interface UseZoomState {
  dragEnd: () => void;
  dragStart: (
    zoom: ProvidedZoom<SVGSVGElement> & ZoomState
  ) => (e: React.MouseEvent<SVGSVGElement>) => void;
  isDragging: boolean;
  move: (
    zoom: ProvidedZoom<SVGSVGElement> & ZoomState
  ) => (e: React.MouseEvent<SVGSVGElement>) => void;
}

export const useZoom = (): UseZoomState => {
  const [startTranslate, setStartTranslate] = useState<Translate | null>(null);
  const [startPoint, setStartPoint] = useState<Point | null>(null);

  const dragStart = useCallback(
    (zoom: ProvidedZoom<SVGSVGElement> & ZoomState) =>
      (e: React.MouseEvent<SVGSVGElement>): void => {
        if (!isLeftMouseButtonClicked(e)) {
          return;
        }
        const { translateX, translateY } = zoom.transformMatrix;
        setStartPoint(localPoint(e) || null);
        setStartTranslate({ translateX, translateY });
      },
    []
  );

  const move = useCallback(
    (zoom: ProvidedZoom<SVGSVGElement> & ZoomState) =>
      (e: React.MouseEvent<SVGSVGElement>): void => {
        if (!startPoint || !startTranslate) {
          return;
        }
        const currentPoint = localPoint(e);
        const dx = currentPoint
          ? -(startPoint.x - currentPoint.x)
          : -startPoint.x;
        const dy = currentPoint
          ? -(startPoint.y - currentPoint.y)
          : -startPoint.y;

        const translateX = startTranslate.translateX + dx;
        const translateY = startTranslate.translateY + dy;
        zoom.setTranslate({
          translateX,
          translateY
        });
      },
    [startPoint, startTranslate]
  );
  const dragEnd = useCallback((): void => {
    setStartPoint(null);
    setStartTranslate(null);
  }, []);

  return {
    dragEnd,
    dragStart,
    isDragging: Boolean(startPoint && startTranslate),
    move
  };
};
