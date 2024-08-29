import { scaleBand, scaleLinear, scaleOrdinal } from '@visx/scale';
import { equals, gt, pluck } from 'ramda';

import { LegendScale } from '../Legend/models';
import { getValueByUnit } from '../common/utils';

import { BarType } from './models';

interface Size {
  height: number;
  width: number;
}

interface UseBarStackProps {
  data: Array<BarType>;
  height: number;
  legendRef;
  size: number;
  titleRef;
  unit?: 'percentage' | 'number';
  variant?: 'vertical' | 'horizontal';
  width: number;
}
interface UseBarStackState {
  barSize: Size;
  colorScale;
  input;
  isVerticalBar: boolean;
  keys: Array<string>;
  legendScale: LegendScale;
  svgContainerSize: Size;
  total: number;
  xScale;
  yScale;
}

const useResponsiveBarStack = ({
  data,
  variant,
  height,
  width,
  unit = 'number',
  titleRef,
  legendRef,
  size
}: UseBarStackProps): UseBarStackState => {
  const isVerticalBar = equals(variant, 'vertical');

  const heightOfTitle = titleRef.current?.offsetHeight || 0;
  const widthOfLegend = legendRef.current?.offsetWidth || 0;

  const horizontalGap = widthOfLegend > 0 ? 12 : 0;
  const verticalGap = heightOfTitle > 0 ? 8 : 0;

  const svgContainerSize = {
    height: isVerticalBar ? height - heightOfTitle - verticalGap : size,
    width: isVerticalBar ? size : width - widthOfLegend - horizontalGap
  };

  const barSize = {
    height: gt(height / 2, svgContainerSize.height - 16)
      ? svgContainerSize.height - 16
      : svgContainerSize.height - 46,
    width: svgContainerSize.width - 16
  };

  const total = Math.floor(data.reduce((acc, { value }) => acc + value, 0));

  const yScale = isVerticalBar
    ? scaleLinear({
        domain: [0, total]
      })
    : scaleBand({
        domain: [0, 0],
        padding: 0
      });

  const xScale = isVerticalBar
    ? scaleBand({
        domain: [0, 0],
        padding: 0
      })
    : scaleLinear({
        domain: [0, total]
      });

  const keys = pluck('label', data);

  const colorsRange = pluck('color', data);

  const colorScale = scaleOrdinal({
    domain: keys,
    range: colorsRange
  });

  const legendScale = {
    domain: data.map(({ value }) => getValueByUnit({ total, unit, value })),
    range: colorsRange
  };

  const xMax = barSize.width;
  const yMax = barSize.height;

  xScale.rangeRound([0, xMax]);
  yScale.range([yMax, 0]);

  const input = data.reduce((acc, { label, value }) => {
    acc[label] = value;

    return acc;
  }, {});

  return {
    barSize,
    colorScale,
    input,
    isVerticalBar,
    keys,
    legendScale,
    svgContainerSize,
    total,
    xScale,
    yScale
  };
};

export default useResponsiveBarStack;
