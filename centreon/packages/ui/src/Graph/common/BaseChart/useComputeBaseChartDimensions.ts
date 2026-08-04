import { equals, isNil } from 'ramda';
import type { RefCallback } from 'react';
import useResizeObserver from 'use-resize-observer';

import { useMarginTop } from '../useMarginTop';
import { computeGElementMarginLeft } from '../utils';

export const extraMargin = 10;

interface UseComputeBaseChartDimensionsProps {
  hasSecondUnit?: boolean;
  height: number | null;
  legendDisplay?: boolean;
  legendHeight?: number;
  legendPlacement?: string;
  width: number;
  maxLeftAxisCharacters: number;
  maxRightAxisCharacters: number;
  title?: string;
  units: Array<string>;
}

interface UseComputeBaseChartDimensionsState {
  graphHeight: number;
  graphWidth: number;
  legendRef: RefCallback<Element>;
  titleRef: RefCallback<Element>;
}

export const useComputeBaseChartDimensions = ({
  width,
  height,
  legendDisplay,
  legendPlacement,
  hasSecondUnit,
  legendHeight,
  maxLeftAxisCharacters,
  maxRightAxisCharacters,
  units,
  title
}: UseComputeBaseChartDimensionsProps): UseComputeBaseChartDimensionsState => {
  const {
    ref: legendRef,
    width: legendRefWidth,
    height: legendRefHeight
  } = useResizeObserver();
  const { ref: titleRef, height: titleRefHeight } = useResizeObserver();

  const currentLegendHeight = legendHeight ?? (legendRefHeight || 0);

  const marginTop = useMarginTop({ title, units });

  const legendBoundingHeight =
    !equals(legendDisplay, false) &&
    (isNil(legendPlacement) || equals(legendPlacement, 'bottom'))
      ? currentLegendHeight
      : 0;
  const legendBoundingWidth =
    !equals(legendDisplay, false) &&
    (equals(legendPlacement, 'left') || equals(legendPlacement, 'right'))
      ? legendRefWidth || 0
      : 0;

  const leftMargin = computeGElementMarginLeft({
    hasSecondUnit,
    maxCharacters: maxLeftAxisCharacters
  });
  const rightAxisLabelWidth = hasSecondUnit
    ? maxRightAxisCharacters * 4 + 2
    : 0;

  const graphWidth =
    width > 0
      ? width - leftMargin - rightAxisLabelWidth - legendBoundingWidth
      : 0;
  const graphHeight =
    (height || 0) > 0
      ? (height || 0) - marginTop - legendBoundingHeight - (titleRefHeight || 0)
      : 0;

  return {
    graphHeight,
    graphWidth,
    legendRef,
    titleRef
  };
};
