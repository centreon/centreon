import { MutableRefObject, useRef } from 'react';

import { equals, isNil } from 'ramda';

import { margin } from '../../Chart/common';
import { margins } from '../margins';

export const extraMargin = 10;

interface UseComputeBaseChartDimensionsProps {
  hasSecondUnit?: boolean;
  height: number | null;
  legendDisplay?: boolean;
  legendHeight?: number;
  legendPlacement?: string;
  width: number;
  maxAxisCharacters: number;
}

interface UseComputeBaseChartDimensionsState {
  graphHeight: number;
  graphWidth: number;
  legendRef: MutableRefObject<HTMLDivElement | null>;
  titleRef: MutableRefObject<HTMLDivElement | null>;
}

export const useComputeBaseChartDimensions = ({
  width,
  height,
  legendDisplay,
  legendPlacement,
  hasSecondUnit,
  legendHeight,
  maxAxisCharacters
}: UseComputeBaseChartDimensionsProps): UseComputeBaseChartDimensionsState => {
  const legendRef = useRef<HTMLDivElement | null>(null);
  const titleRef = useRef<HTMLDivElement | null>(null);

  const currentLegendHeight =
    legendHeight ?? (legendRef.current?.getBoundingClientRect().height || 0);

  const legendBoundingHeight =
    !equals(legendDisplay, false) &&
    (isNil(legendPlacement) || equals(legendPlacement, 'bottom'))
      ? currentLegendHeight
      : 0;
  const legendBoundingWidth =
    !equals(legendDisplay, false) &&
    (equals(legendPlacement, 'left') || equals(legendPlacement, 'right'))
      ? legendRef.current?.getBoundingClientRect().width || 0
      : 0;

  const graphWidth =
    width > 0
      ? width -
        (hasSecondUnit ? maxAxisCharacters * 2 : maxAxisCharacters) * 6 -
        (hasSecondUnit ? margins.left * 0.8 : margin.left) -
        legendBoundingWidth
      : 0;
  const graphHeight =
    (height || 0) > 0
      ? (height || 0) -
        margin.top -
        legendBoundingHeight -
        (titleRef.current?.getBoundingClientRect().height || 0) -
        5
      : 0;

  return {
    graphHeight,
    graphWidth,
    legendRef,
    titleRef
  };
};
