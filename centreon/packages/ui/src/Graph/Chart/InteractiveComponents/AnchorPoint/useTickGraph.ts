import { MutableRefObject, useEffect, useMemo, useRef, useState } from 'react';

import { ScaleLinear } from 'd3-scale';
import { useAtomValue } from 'jotai';

import useAxisY from '../../../common/Axes/useAxisY';
import { getTimeValue } from '../../../common/timeSeries';
import { Line, TimeValue } from '../../../common/timeSeries/models';
import { margin } from '../../common';
import { mousePositionAtom } from '../interactionWithGraphAtoms';

interface AnchorPointResult {
  positionX?: number;
  positionY?: number;
  tickAxisBottom: Date | null;
  tickAxisLeft: string | null;
  tickAxisRight: string | null;
  guidingLinesRef: MutableRefObject<SVGGElement | null>;
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
  const guidingLinesRef = useRef<SVGGElement | null>(null);
  const [tickAxisBottom, setTickAxisBottom] = useState<Date | null>(null);
  const [tickAxisLeft, setTickAxisLeft] = useState<string | null>(null);
  const [tickAxisRight, setTickAxisRight] = useState<string | null>(null);

  const { axisRight, axisLeft } = useAxisY({ data: { baseAxis, lines } });

  const mousePosition = useAtomValue(mousePositionAtom);

  const paddingLeftString = useMemo(
    () =>
      (
        guidingLinesRef.current?.parentElement?.parentElement?.attributes
          ?.transform.value || ''
      ).match(/translate\(([0-9\.]+), ([0-9\.]+)\)/)?.[1] || '0',
    [
      guidingLinesRef.current?.parentElement?.parentElement?.attributes
        ?.transform.value
    ]
  );

  const positionX = mousePosition
    ? mousePosition[0] - Number(paddingLeftString) - 1
    : undefined;
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
    const timeTickValue = mousePosition
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
    tickAxisRight,
    guidingLinesRef
  };
};
export default useTickGraph;
