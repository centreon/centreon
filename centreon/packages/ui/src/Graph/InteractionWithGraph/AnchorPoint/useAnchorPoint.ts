import { MutableRefObject, useEffect } from 'react';

import { Event } from '@visx/visx';
import { ScaleLinear } from 'd3-scale';
import { useAtomValue, useUpdateAtom } from 'jotai/utils';
import { equals, isEmpty, isNil, not } from 'ramda';

import { margin } from '../../common';
import { getMetrics, getTimeValue } from '../../timeSeries';
import { TimeValue } from '../../timeSeries/models';
import {
  MousePosition,
  changeMousePositionAndTimeValueDerivedAtom,
  mousePositionAtom,
  timeValueAtom
} from '../mouseTimeValueAtoms';

interface AnchorPointResult {
  position: MousePosition;
  positionX?: number;
  positionY?: number;
  timeTick?: Date;
}

interface Props {
  event?: MouseEvent;
  graphSvgRef: MutableRefObject<SVGSVGElement | null>;
  timeSeries: Array<TimeValue>;
  xScale: ScaleLinear<number, number>;
}

const useAnchorPoint = ({
  event,
  graphSvgRef,
  timeSeries,
  xScale
}: Props): AnchorPointResult => {
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
  const timeTick = containsMetrics
    ? new Date(mousePositionTimeTick)
    : undefined;

  const positionX = position ? position[0] - margin.left : undefined;
  const positionY = position ? position[1] - margin.top : undefined;

  return {
    position: mousePosition,
    positionX,
    positionY,
    timeTick
  };
};
export default useAnchorPoint;
