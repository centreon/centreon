import { equals, isEmpty, pluck, reject } from 'ramda';

import { LegendScale } from '../Legend/models';
import { getValueByUnit } from '../common/utils';

import { ArcType } from './models';

interface ResponsivePieProps {
  data: Array<ArcType>;
  defaultInnerRadius: number;
  height: number;
  legendRef;
  titleRef;
  unit: 'percentage' | 'number';
  width: number;
}

interface ResponsivePieState {
  areAllValuesNull: boolean;
  half: number;
  innerRadius: number;
  isContainsExactlyOneNonZeroValue: boolean;
  legendScale: LegendScale;
  svgContainerSize: number;
  svgSize: number;
  svgWrapperWidth: number;
  total: number;
}
export const useResponsivePie = ({
  titleRef,
  legendRef,
  height,
  width,
  data,
  unit,
  defaultInnerRadius
}: ResponsivePieProps): ResponsivePieState => {
  const heightOfTitle = titleRef.current?.offsetHeight || 0;
  const widthOfLegend = legendRef.current?.offsetWidth || 0;

  const horizontalGap = widthOfLegend > 0 ? 16 : 0;
  const verticalGap = heightOfTitle > 0 ? 8 : 0;

  const svgContainerSize = Math.min(
    height - heightOfTitle - verticalGap,
    width - widthOfLegend - horizontalGap
  );
  const svgWrapperWidth = svgContainerSize;

  const outerRadius = Math.min(32, svgContainerSize / 6);

  const svgSize = svgContainerSize - outerRadius;

  const half = svgSize / 2;

  const total = Math.floor(data.reduce((acc, { value }) => acc + value, 0));

  const innerRadius = Math.min(defaultInnerRadius, svgSize / 5);

  const legendScale = {
    domain: data.map(({ value }) => getValueByUnit({ total, unit, value })),
    range: pluck('color', data)
  };

  const values = pluck('value', data);

  const isContainsExactlyOneNonZeroValue = equals(
    reject((value) => equals(value, 0), values).length,
    1
  );

  const areAllValuesNull = isEmpty(reject((value) => equals(value, 0), values));

  return {
    areAllValuesNull,
    half,
    innerRadius,
    isContainsExactlyOneNonZeroValue,
    legendScale,
    svgContainerSize,
    svgSize,
    svgWrapperWidth,
    total
  };
};
