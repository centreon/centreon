import { PatternLines } from '@visx/pattern';
import { ScaleLinear } from 'd3-scale';
import { prop } from 'ramda';

import { useTheme } from '@mui/material/styles';

import { getTime } from '../../../timeSeries/index';
import { TimeValue } from '../../../timeSeries/models';
import { adjustGraphData } from '../../../helpers/index';
import { GraphData } from '../../../models';

import BasicThreshold from './BasicThreshold';
import useDataThreshold from './useDataThreshold';

interface Props {
  data: GraphData;
  display: boolean;
  graphHeight: number;
  leftScale: ScaleLinear<number, number>;
  rightScale: ScaleLinear<number, number>;
  xScale: ScaleLinear<number, number>;
}

const ThresholdWithPatternLines = ({
  graphHeight,
  data,
  display,
  leftScale,
  rightScale,
  xScale
}: Props): JSX.Element | null => {
  const theme = useTheme();

  const { lines, timeSeries } = adjustGraphData(data);

  const { dataY0, dataY1, displayThreshold } = useDataThreshold({
    display,
    leftScale,
    lines,
    rightScale
  });

  if (!dataY0 || !dataY1 || !displayThreshold) {
    return null;
  }
  const { metric: metricY0, yScale: y0Scale } = dataY0;
  const { metric: metricY1, yScale: y1Scale } = dataY1;

  const getX = (timeValue: TimeValue): number => xScale(getTime(timeValue));

  const getY0 = (timeValue: TimeValue): number =>
    y0Scale(prop(metricY0, timeValue)) ?? null;

  const getY1 = (timeValue: TimeValue): number =>
    y1Scale(prop(metricY1, timeValue)) ?? null;

  return (
    <>
      <BasicThreshold
        fillAboveArea={"url('#lines')"}
        fillBelowArea={"url('#lines')"}
        fillOpacity={0.8}
        getX={getX}
        getY0={getY0}
        getY1={getY1}
        graphHeight={graphHeight}
        timeSeries={timeSeries}
      />
      <PatternLines
        data-testid="patternLinesExclusionPeriods"
        height={5}
        id="lines"
        orientation={['diagonal']}
        stroke={theme.palette.text.primary}
        strokeWidth={1}
        width={5}
      />
    </>
  );
};

export default ThresholdWithPatternLines;
