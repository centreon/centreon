import { LegendScale } from '../Legend/models';
import { getValueByUnit } from '../common/utils';

import { ArcType } from './models';

interface ResponsivePieProps {
  data: Array<ArcType>;
  height: number;
  legendRef;
  titleRef;
  unit: 'percentage' | 'number';
  width: number;
}

interface ResponsivePieState {
  half: number;
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
  unit
}: ResponsivePieProps): ResponsivePieState => {
  const heightOfTitle = titleRef.current?.offsetHeight || 0;
  const widthOfLegend = legendRef.current?.offsetWidth || 0;

  const svgWrapperWidth = width - widthOfLegend;
  const svgContainerSize = Math.min(
    height - heightOfTitle,
    width - widthOfLegend
  );

  const svgSize = svgContainerSize - 32;

  const half = svgSize / 2;

  const total = Math.floor(data.reduce((acc, { value }) => acc + value, 0));

  const legendScale = {
    domain: data.map(({ value }) => getValueByUnit({ total, unit, value })),
    range: data.map(({ color }) => color)
  };

  return {
    half,
    legendScale,
    svgContainerSize,
    svgSize,
    svgWrapperWidth,
    total
  };
};
