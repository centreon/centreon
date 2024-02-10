import { scaleBand, scaleLinear, scaleOrdinal } from '@visx/scale';
import { equals } from 'ramda';

import { getValueByUnit } from '../common/utils';

import { BarType } from './models';

interface useBarStackProps {
  barHeight: number;
  barWidth: number;
  data: Array<BarType>;
  unit?: 'Percentage' | 'Number';
  variant?: 'Vertical' | 'Horizontal';
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
  unit = 'Number'
}: useBarStackProps): useBarStackState => {
  const width = equals(variant, 'Vertical') ? barWidth : barHeight;
  const height = equals(variant, 'Vertical') ? barHeight : barWidth;

  const total = Math.floor(data.reduce((acc, { value }) => acc + value, 0));

  const yScale = equals(variant, 'Vertical')
    ? scaleLinear({
        domain: [0, total],
        nice: true
      })
    : scaleBand({
        domain: [0, 0],
        padding: 0
      });

  const xScale = equals(variant, 'Vertical')
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
