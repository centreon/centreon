import { scaleBand, scaleLinear, scaleOrdinal } from '@visx/scale';
import { equals } from 'ramda';

import { getValueByUnit } from '../common/utils';

import { BarType } from './models';

interface useBarStackProps {
  barHeight: number;
  barWidth: number;
  data: Array<BarType>;
  unit?: 'percentage' | 'number';
  variant?: 'vertical' | 'horizontal';
}
interface useBarStackState {
  colorScale;
  height: number;
  input;
  keys: Array<string>;
  legendScale;
  total: number;
  width: number;
  xScale;
  yScale;
}

const useResponsiveBarStack = ({
  data,
  variant,
  barWidth,
  barHeight,
  unit = 'number'
}: useBarStackProps): useBarStackState => {
  const isVerticalBar = equals(variant, 'vertical');

  const width = isVerticalBar ? barHeight : barWidth;
  const height = isVerticalBar ? 250 : barHeight;

  const total = Math.floor(data.reduce((acc, { value }) => acc + value, 0));

  const yScale = isVerticalBar
    ? scaleLinear({
        domain: [0, total],
        nice: true
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
        domain: [0, total],
        nice: true
      });

  const keys = data.map(({ label }) => label);

  const colorsRange = data.map(({ color }) => color);

  const colorScale = scaleOrdinal({
    domain: keys,
    range: colorsRange
  });

  const legendScale = {
    domain: data.map(({ value }) => getValueByUnit({ total, unit, value })),
    range: colorsRange
  };

  const xMax = width;
  const yMax = height;

  xScale.rangeRound([0, xMax]);
  yScale.range([yMax, 0]);

  const input = data.reduce((acc, { label, value }) => {
    acc[label] = value;

    return acc;
  }, {});

  return {
    colorScale,
    height,
    input,
    keys,
    legendScale,
    total,
    width,
    xScale,
    yScale
  };
};

export default useResponsiveBarStack;
