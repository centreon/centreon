import type { ScaleLinear } from 'd3-scale';
import { useAtomValue } from 'jotai';
import { type MutableRefObject, useEffect, useState } from 'react';

import useAxisY from '../../../common/Axes/useAxisY';
import { getTimeValue } from '../../../common/timeSeries';
import type { Line, TimeValue } from '../../../common/timeSeries/models';
import {
  computeGElementMarginLeft,
  computPixelsToShiftMouse
} from '../../../common/utils';
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
  hasSecondUnit?: boolean;
  maxLeftAxisCharacters: number;
  hasUnit?: boolean;
}

const useTickGraph = ({
  timeSeries,
  xScale,
  leftScale,
  rightScale,
  lines = [],
  baseAxis = 1000,
  hasSecondUnit,
  maxLeftAxisCharacters,
  hasUnit
}: Props): AnchorPointResult => {
  const [tickAxisBottom, setTickAxisBottom] = useState<Date | null>(null);
  const [tickAxisLeft, setTickAxisLeft] = useState<string | null>(null);
  const [tickAxisRight, setTickAxisRight] = useState<string | null>(null);

  const { axisRight, axisLeft } = useAxisY({ data: { baseAxis, lines } });

  const mousePosition = useAtomValue(mousePositionAtom);

  const positionX = mousePosition
    ? mousePosition[0] -
      computeGElementMarginLeft({
        hasSecondUnit,
        maxCharacters: maxLeftAxisCharacters
      })
    : undefined;
  const positionY = mousePosition
    ? mousePosition[1] - (hasUnit ? 29 : 4)
    : undefined;

  useEffect(() => {
    if (!mousePosition) {
      setTickAxisBottom(null);
      setTickAxisLeft(null);
      setTickAxisRight(null);

      return;
    }
    const pixelToShift = computPixelsToShiftMouse(xScale);
    const mousePositionTimeTick = mousePosition
      ? getTimeValue({
          marginLeft: computeGElementMarginLeft({
            hasSecondUnit,
            maxCharacters: maxLeftAxisCharacters
          }),
          timeSeries,
          x: mousePosition[0] - pixelToShift,
          xScale
        })?.timeTick
      : 0;
    const timeTickValue = mousePosition
      ? new Date(mousePositionTimeTick || 0)
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
