import { curveBasis } from '@visx/curve';
import { LinePath } from '@visx/shape';
import { ScaleLinear } from 'd3-scale';
import { prop } from 'ramda';

import { useTheme } from '@mui/material/styles';

import { getTime } from '../../../timeSeries/index';
import { TimeValue } from '../../../timeSeries/models';

import BasicThreshold from './BasicThreshold';
import { Data } from './models';

interface Props {
  dataY0: Data;
  dataY1: Data;
  graphHeight: number;
  timeSeries: Array<TimeValue>;
  variation?: number;
  xScale: ScaleLinear<number, number>;
}

const ThresholdLines = ({
  dataY0,
  dataY1,
  timeSeries,
  xScale,
  graphHeight,
  variation
}: Props): JSX.Element => {
  const theme = useTheme();

  const { lineColor: lineColorY0, metric: metricY0, yScale: y0Scale } = dataY0;
  const { lineColor: lineColorY1, metric: metricY1, yScale: y1Scale } = dataY1;

  const getX = (timeValue: TimeValue): number =>
    xScale(getTime(timeValue)) as number;

  if (!variation) {
    const getY0 = (timeValue: TimeValue): number =>
      y0Scale(prop(metricY0, timeValue)) ?? null;
    const getY1 = (timeValue: TimeValue): number =>
      y1Scale(prop(metricY1, timeValue)) ?? null;

    return (
      <BasicThreshold
        getX={getX}
        getY0={getY0}
        getY1={getY1}
        graphHeight={graphHeight}
        lineColorY0={lineColorY0}
        lineColorY1={lineColorY1}
        timeSeries={timeSeries}
      />
    );
  }

  const getY0 = (timeValue: TimeValue): number =>
    y0Scale(prop(metricY0, timeValue) + variation) ?? null;

  const getY1 = (timeValue: TimeValue): number =>
    y1Scale(prop(metricY1, timeValue) - variation) ?? null;

  const props = {
    curve: curveBasis,
    data: timeSeries,
    stroke: theme.palette.secondary.main,
    strokeDasharray: 5,
    strokeOpacity: 0.8,
    x: getX
  };

  return (
    <>
      <BasicThreshold
        getX={getX}
        getY0={getY0}
        getY1={getY1}
        graphHeight={graphHeight}
        lineColorY0={lineColorY0}
        lineColorY1={lineColorY1}
        timeSeries={timeSeries}
      />

      <LinePath {...props} y={getY0} />
      <LinePath {...props} y={getY1} />
    </>
  );
};

export default ThresholdLines;
