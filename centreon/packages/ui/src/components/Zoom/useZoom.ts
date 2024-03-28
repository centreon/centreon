import { useCallback, useState } from 'react';

import { Point, ProvidedZoom, Translate } from '@visx/zoom/lib/types';
import { equals } from 'ramda';

import { localPoint } from './localPoint';
import { ZoomState } from './models';

interface UseZoomState {
  dragEnd: () => void;
  dragStart: (zoom: ProvidedZoom<SVGSVGElement> & ZoomState) => (e) => void;
  isDragging: boolean;
  move: (zoom: ProvidedZoom<SVGSVGElement> & ZoomState) => (e) => void;
}

export const useZoom = (): UseZoomState => {
  const [startTranslate, setStartTranslate] = useState<Translate | undefined>(
    undefined
  );
  const [startPoint, setStartPoint] = useState<Point | undefined>(undefined);

  const dragStart = useCallback(
    (zoom: ProvidedZoom<SVGSVGElement> & ZoomState) =>
      (e): void => {
        if (!equals(e.nativeEvent.which, 1)) {
          return;
        }
        const { translateX, translateY } = zoom.transformMatrix;
        setStartPoint(localPoint(e) || undefined);
        setStartTranslate({ translateX, translateY });
      },
    []
  );

  const move = useCallback(
    (zoom: ProvidedZoom<SVGSVGElement> & ZoomState) =>
      (e): void => {
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
    setStartPoint(undefined);
    setStartTranslate(undefined);
  }, []);

  return {
    dragEnd,
    dragStart,
    isDragging: Boolean(startPoint && startTranslate),
    move
  };
};
