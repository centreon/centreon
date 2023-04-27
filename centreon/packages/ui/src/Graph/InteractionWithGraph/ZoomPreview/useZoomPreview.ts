import { useEffect, useState } from 'react';

import { Event } from '@visx/visx';
import { gte, isNil, lt } from 'ramda';

import { margin } from '../../common';

import { ZoomBoundaries } from './models';

interface ZoomPreview {
  zoomBarWidth: number;
  zoomBoundaries: ZoomBoundaries;
}

const useZoomPreview = ({ eventMouseDown, movingMouseX }: any): ZoomPreview => {
  const [zoomBoundaries, setZoomBoundaries] = useState<any | null>(null);
  const mousePoint = Event.localPoint(eventMouseDown);

  const mouseDownX = mousePoint ? mousePoint.x - margin.left : null;

  useEffect(() => {
    if (isNil(eventMouseDown)) {
      setZoomBoundaries(null);

      return;
    }
    if (!isNil(movingMouseX)) {
      return;
    }
    setZoomBoundaries({
      end: mouseDownX,
      start: mouseDownX
    });
  }, [eventMouseDown]);

  useEffect(() => {
    if (isNil(mouseDownX) || isNil(movingMouseX)) {
      return;
    }
    setZoomBoundaries({
      end: gte(movingMouseX, mouseDownX) ? movingMouseX : mouseDownX,
      start: lt(movingMouseX, mouseDownX) ? movingMouseX : mouseDownX
    });
  }, [movingMouseX]);

  const zoomBarWidth = Math.abs(
    (zoomBoundaries?.end || 0) - (zoomBoundaries?.start || 0)
  );

  return { zoomBarWidth, zoomBoundaries };
};

export default useZoomPreview;
