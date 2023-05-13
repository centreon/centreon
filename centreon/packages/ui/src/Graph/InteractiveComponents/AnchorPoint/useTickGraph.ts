import { useEffect, useState } from 'react';

import { ScaleLinear } from 'd3-scale';
import { useAtomValue } from 'jotai';
import { isEmpty, isNil, not } from 'ramda';

import { margin } from '../../common';
import { getMetrics, getTimeValue } from '../../timeSeries';
import { TimeValue } from '../../timeSeries/models';
import { mousePositionAtom, timeValueAtom } from '../interactionWithGraphAtoms';

interface AnchorPointResult {
  positionX?: number;
  positionY?: number;
  timeTick: Date | null;
}

interface Props {
  timeSeries: Array<TimeValue>;
  xScale: ScaleLinear<number, number>;
}

const useTickGraph = ({ timeSeries, xScale }: Props): AnchorPointResult => {
  const [timeTick, setTimeTick] = useState<Date | null>(null);
  const mousePosition = useAtomValue(mousePositionAtom);
  const timeValueData = useAtomValue(timeValueAtom);

  const metrics = getMetrics(timeValueData as TimeValue);

  const containsMetrics = not(isNil(metrics)) && not(isEmpty(metrics));

  const positionX = mousePosition ? mousePosition[0] - margin.left : undefined;
  const positionY = mousePosition ? mousePosition[1] - margin.top : undefined;

  useEffect(() => {
    if (!mousePosition) {
      setTimeTick(null);

      return;
    }
    const mousePositionTimeTick = mousePosition
      ? getTimeValue({ timeSeries, x: mousePosition[0], xScale }).timeTick
      : 0;
    const timeTickValue = containsMetrics
      ? new Date(mousePositionTimeTick)
      : null;

    setTimeTick(timeTickValue);
  }, [mousePosition]);

  return {
    positionX,
    positionY,
    timeTick
  };
};
export default useTickGraph;
