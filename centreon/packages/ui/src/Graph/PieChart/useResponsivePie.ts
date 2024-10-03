import { equals, pluck, reject } from 'ramda';

import { LegendScale } from '../Legend/models';
import { getValueByUnit } from '../common/utils';

import { ArcType } from './models';

interface ResponsivePieProps {
  data: Array<ArcType>;
  defaultInnerRadius: number;
  height: number;
  innerRadiusNoLimit: boolean;
  legendRef;
  titleRef;
  unit: 'percentage' | 'number';
  width: number;
}

interface ResponsivePieState {
  half: number;
  innerRadius: number;
  isContainsExactlyOneNonZeroValue: boolean;
  legendScale: LegendScale;
  svgContainerSize: number;
  svgSize: number;
  total: number;
}
export const useResponsivePie = ({
  titleRef,
  legendRef,
  height,
  width,
  data,
  unit,
  defaultInnerRadius,
  innerRadiusNoLimit
}: ResponsivePieProps): ResponsivePieState => {
  const heightOfTitle = titleRef.current?.offsetHeight || 0;
  const widthOfLegend = legendRef.current?.offsetWidth || 0;

  const horizontalGap = widthOfLegend > 0 ? 16 : 0;
  const verticalGap = heightOfTitle > 0 ? 8 : 0;

  const svgContainerSize = Math.min(
    height - heightOfTitle - verticalGap,
    width - widthOfLegend - horizontalGap
  );

  const outerRadius = Math.min(32, svgContainerSize / 6);

  const svgSize = svgContainerSize - outerRadius;

  const half = svgSize / 2;

  const total = Math.floor(data.reduce((acc, { value }) => acc + value, 0));

  let innerRadius = Math.min(defaultInnerRadius, svgSize / 5);

  if (innerRadiusNoLimit) {
    innerRadius = half * defaultInnerRadius * 0.01;
  }

  const legendScale = {
    domain: data.map(({ value }) => getValueByUnit({ total, unit, value })),
    range: pluck('color', data)
  };

  const values = pluck('value', data);

  const isContainsExactlyOneNonZeroValue = equals(
    reject((value) => equals(value, 0), values).length,
    1
  );

  return {
    half,
    innerRadius,
    isContainsExactlyOneNonZeroValue,
    legendScale,
    svgContainerSize,
    svgSize,
    total
  };
};
