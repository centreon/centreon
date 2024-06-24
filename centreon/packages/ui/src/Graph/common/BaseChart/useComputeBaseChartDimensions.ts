import { MutableRefObject, useRef } from 'react';

import { equals, isNil } from 'ramda';

import { margin } from '../../LineChart/common';

export const extraMargin = 10;

interface UseComputeBaseChartDimensionsProps {
  hasSecondUnit?: boolean;
  height: number | null;
  legendDisplay?: boolean;
  legendPlacement?: string;
  width: number;
}

interface UseComputeBaseChartDimensionsState {
  graphHeight: number;
  graphWidth: number;
  legendRef: MutableRefObject<HTMLDivElement | null>;
}

export const useComputeBaseChartDimensions = ({
  width,
  height,
  legendDisplay,
  legendPlacement,
  hasSecondUnit
}: UseComputeBaseChartDimensionsProps): UseComputeBaseChartDimensionsState => {
  const legendRef = useRef<HTMLDivElement | null>(null);

  const legendBoundingHeight =
    !equals(legendDisplay, false) &&
    (isNil(legendPlacement) || equals(legendPlacement, 'bottom'))
      ? legendRef.current?.getBoundingClientRect().height || 0
      : 0;
  const legendBoundingWidth =
    !equals(legendDisplay, false) &&
    (equals(legendPlacement, 'left') || equals(legendPlacement, 'right'))
      ? legendRef.current?.getBoundingClientRect().width || 0
      : 0;

  const graphWidth =
    width > 0
      ? width -
        margin.left -
        (hasSecondUnit ? margin.right : 8) -
        extraMargin -
        legendBoundingWidth
      : 0;
  const graphHeight =
    (height || 0) > 0
      ? (height || 0) - margin.top - 5 - legendBoundingHeight
      : 0;

  return {
    graphHeight,
    graphWidth,
    legendRef
  };
};
