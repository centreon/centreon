import { MutableRefObject, useEffect, useState } from 'react';

import { Event } from '@visx/visx';
import { ScaleLinear } from 'd3-scale';
import { useAtomValue, useSetAtom } from 'jotai';
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
import {
  eventMouseDownAtom,
  eventMouseMovingAtom
} from '../interactionWithGraphAtoms';

interface AnchorPointResult {
  positionX?: number;
  positionY?: number;
  timeTick?: Date;
}

interface Props {
  graphSvgRef: MutableRefObject<SVGSVGElement | null>;
  timeSeries: Array<TimeValue>;
  xScale: ScaleLinear<number, number>;
}

const useAnchorPoint = ({
  graphSvgRef,
  timeSeries,
  xScale
}: Props): AnchorPointResult => {
  const [position, setPosition] = useState<null | MousePosition>(null);

  const eventMouseMoving = useAtomValue(eventMouseMovingAtom);
  const eventMouseDown = useAtomValue(eventMouseDownAtom);

  const mousePosition = useAtomValue(mousePositionAtom);
  const timeValueData = useAtomValue(timeValueAtom);

  const changeMousePositionAndTimeValue = useSetAtom(
    changeMousePositionAndTimeValueDerivedAtom
  );

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
    if (eventMouseDown) {
      return;
    }
    const mousePoint = eventMouseMoving
      ? Event.localPoint(
          graphSvgRef?.current as SVGSVGElement,
          eventMouseMoving
        )
      : null;
    setPosition(mousePoint ? [mousePoint.x, mousePoint.y] : null);
  }, [eventMouseDown, eventMouseMoving]);

  useEffect(() => {
    if (equals(position, mousePosition) && position) {
      return;
    }
    updateMousePosition(position);
  }, [position]);

  return {
    positionX,
    positionY,
    timeTick
  };
};
export default useAnchorPoint;
