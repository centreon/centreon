import { useEffect, useState } from 'react';

import { ScaleLinear } from 'd3-scale';
import { useAtomValue } from 'jotai';
import { isEmpty, isNil, not } from 'ramda';

import useAxisY from '../../BasicComponents/Axes/useAxisY';
import { margin } from '../../common';
import { getMetrics, getTimeValue } from '../../timeSeries';
import { Line, TimeValue } from '../../timeSeries/models';
import { mousePositionAtom, timeValueAtom } from '../interactionWithGraphAtoms';

interface AnchorPointResult {
  positionX?: number;
  positionY?: number;
  tickAxisBottom: Date | null;
  tickAxisLeft: string | null;
  tickAxisRight: string | null;
}

interface Props {
  baseAxis?: number;
  leftScale?: ScaleLinear<number, number>;
  lines?: Array<Line>;
  rightScale?: ScaleLinear<number, number>;
  timeSeries: Array<TimeValue>;
  xScale: ScaleLinear<number, number>;
}

const useTickGraph = ({
  timeSeries,
  xScale,
  leftScale,
  rightScale,
  lines = [],
  baseAxis = 1000
}: Props): AnchorPointResult => {
  const [tickAxisBottom, setTickAxisBottom] = useState<Date | null>(null);
  const [tickAxisLeft, setTickAxisLeft] = useState<string | null>(null);
  const [tickAxisRight, setTickAxisRight] = useState<string | null>(null);

  const { axisRight, axisLeft } = useAxisY({ data: { baseAxis, lines } });

  const mousePosition = useAtomValue(mousePositionAtom);
  const timeValueData = useAtomValue(timeValueAtom);

  const metrics = getMetrics(timeValueData as TimeValue);

  const containsMetrics = not(isNil(metrics)) && not(isEmpty(metrics));

  const positionX = mousePosition ? mousePosition[0] - margin.left : undefined;
  const positionY = mousePosition ? mousePosition[1] - margin.top : undefined;

  useEffect(() => {
    if (!mousePosition) {
      setTickAxisBottom(null);
      setTickAxisLeft(null);
      setTickAxisRight(null);

      return;
    }
    const mousePositionTimeTick = mousePosition
      ? getTimeValue({ timeSeries, x: mousePosition[0], xScale }).timeTick
      : 0;
    const timeTickValue = containsMetrics
      ? new Date(mousePositionTimeTick)
      : null;

    setTickAxisBottom(timeTickValue);

    const valueTickAxisLeft = leftScale?.invert(positionY);
    const formattedTickAxisLeft = axisLeft?.tickFormat?.(valueTickAxisLeft);

    setTickAxisLeft(formattedTickAxisLeft);

    if (!axisRight.display) {
      setTickAxisRight(null);

      return;
    }
    const valueTickAxisRight = rightScale?.invert(positionY);
    const formattedTickAxisRight = axisRight?.tickFormat?.(valueTickAxisRight);
    setTickAxisRight(formattedTickAxisRight);
  }, [mousePosition]);

  return {
    positionX,
    positionY,
    tickAxisBottom,
    tickAxisLeft,
    tickAxisRight
  };
};
export default useTickGraph;
