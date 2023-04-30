import { useEffect } from 'react';

import { Event } from '@visx/visx';
import { useAtomValue, useUpdateAtom } from 'jotai/utils';
import { equals, isEmpty, isNil, not } from 'ramda';

import { getMetrics, getTimeValue } from '../../timeSeries';
import { TimeValue } from '../../timeSeries/models';
import { margin } from '../../common';
import {
  MousePosition,
  changeMousePositionAndTimeValueDerivedAtom,
  mousePositionAtom,
  timeValueAtom
} from '../../mouseTimeValueAtoms';

const useAnchorPoint = ({
  event,
  graphSvgRef,
  timeSeries,
  xScale
}: any): any => {
  const mousePosition = useAtomValue(mousePositionAtom);
  const timeValueData = useAtomValue(timeValueAtom);

  const changeMousePositionAndTimeValue = useUpdateAtom(
    changeMousePositionAndTimeValueDerivedAtom
  );

  const mousePoint = Event.localPoint(
    graphSvgRef?.current as SVGSVGElement,
    event
  );

  const position: MousePosition = mousePoint
    ? [mousePoint.x, mousePoint.y]
    : null;

  const updateMousePosition = (pointPosition: MousePosition): void => {
    if (isNil(pointPosition)) {
      changeMousePositionAndTimeValue({
        position: null,
        timeValue: null
      });

      return;
    }
    const timeValue = getTimeValue({
      timeSeries,
      x: pointPosition[0],
      xScale
    });

    changeMousePositionAndTimeValue({ position: pointPosition, timeValue });
  };

  useEffect(() => {
    if (equals(position, mousePosition) && position) {
      return;
    }
    updateMousePosition(position);
  }, [position]);

  const metrics = getMetrics(timeValueData as TimeValue);

  const containsMetrics = not(isNil(metrics)) && not(isEmpty(metrics));

  const mousePositionTimeTick = position
    ? getTimeValue({ timeSeries, x: position[0], xScale }).timeTick
    : 0;
  const timeTick = containsMetrics ? new Date(mousePositionTimeTick) : null;

  const positionX = position ? position[0] - margin.left : null;
  const positionY = position ? position[1] - margin.top : null;

  return {
    position: mousePosition,
    positionX,
    positionY,
    timeTick
  };
};
export default useAnchorPoint;
