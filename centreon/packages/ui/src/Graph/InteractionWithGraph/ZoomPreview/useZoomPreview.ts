import { useEffect, useState } from 'react';

import { Event } from '@visx/visx';
import { gte, isNil, lt } from 'ramda';

import { margin } from '../../common';

const useZoomPreview = ({ eventMouseDown, movingMouseX }: any): any => {
  const [zoomBoundaries, setZoomBoundaries] = useState<any | null>(null);
  const mousePoint = Event.localPoint(eventMouseDown);

  const mouseX = mousePoint ? mousePoint.x - margin.left : null;

  useEffect(() => {
    setZoomBoundaries({
      end: mouseX,
      start: mouseX
    });
  }, [eventMouseDown]);

  useEffect(() => {
    if (isNil(mouseX)) {
      return;
    }
    setZoomBoundaries({
      end: gte(movingMouseX, mouseX) ? movingMouseX : mouseX,
      start: lt(movingMouseX, mouseX) ? movingMouseX : mouseX
    });
  }, [movingMouseX]);

  const zoomBarWidth = Math.abs(
    (zoomBoundaries?.end || 0) - (zoomBoundaries?.start || 0)
  );

  return { zoomBarWidth, zoomBoundaries };
};

export default useZoomPreview;
