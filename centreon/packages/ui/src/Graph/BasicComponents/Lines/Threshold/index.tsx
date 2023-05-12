import { prop } from 'ramda';

import { getTime } from '../../../timeSeries/index';
import { TimeValue } from '../../../timeSeries/models';

import BasicThreshold from './BasicThreshold';
import { ThresholdLinesModel } from './models';

const ThresholdLines = ({
  dataY0,
  dataY1,
  timeSeries,
  xScale,
  graphHeight
}: ThresholdLinesModel): JSX.Element => {
  const { lineColor: lineColorY0, metric: metricY0, yScale: y0Scale } = dataY0;
  const { lineColor: lineColorY1, metric: metricY1, yScale: y1Scale } = dataY1;

  const getX = (timeValue: TimeValue): number =>
    xScale(getTime(timeValue)) as number;

  const getY0 = (timeValue: TimeValue): number =>
    y0Scale(prop(metricY0, timeValue)) ?? null;
  const getY1 = (timeValue: TimeValue): number =>
    y1Scale(prop(metricY1, timeValue)) ?? null;

  return (
    <BasicThreshold
      fillAboveArea={lineColorY1}
      fillBelowArea={lineColorY0}
      getX={getX}
      getY0={getY0}
      getY1={getY1}
      graphHeight={graphHeight}
      timeSeries={timeSeries}
    />
  );
};

export default ThresholdLines;
