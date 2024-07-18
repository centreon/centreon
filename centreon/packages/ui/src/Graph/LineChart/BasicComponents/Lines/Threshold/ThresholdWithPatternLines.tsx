import { PatternLines } from '@visx/pattern';
import { ScaleLinear } from 'd3-scale';

import { useTheme } from '@mui/material/styles';

import { adjustGraphData } from '../../../helpers/index';
import { PatternOrientation } from '../../../models';
import { LineChartData } from '../../../../common/models';

import BasicThreshold from './BasicThreshold';
import useScaleThreshold from './useScaleThreshold';

interface Props {
  curve: 'linear' | 'natural' | 'step';
  data: LineChartData;
  graphHeight: number;
  id: string;
  leftScale: ScaleLinear<number, number>;
  orientation?: Array<PatternOrientation>;
  rightScale: ScaleLinear<number, number>;
  xScale: ScaleLinear<number, number>;
}

const ThresholdWithPatternLines = ({
  graphHeight,
  data,
  orientation = ['diagonal'],
  leftScale,
  rightScale,
  xScale,
  id,
  curve
}: Props): JSX.Element | null => {
  const theme = useTheme();

  const { lines, timeSeries } = adjustGraphData(data);

  const result = useScaleThreshold({
    leftScale,
    lines,
    rightScale,
    xScale
  });
  if (!result) {
    return null;
  }

  const { getX, getY0, getY1 } = result;

  return (
    <>
      <BasicThreshold
        curve={curve}
        fillAboveArea={"url('#lines')"}
        fillBelowArea={"url('#lines')"}
        fillOpacity={0.8}
        getX={getX}
        getY0={getY0}
        getY1={getY1}
        graphHeight={graphHeight}
        id={id}
        timeSeries={timeSeries}
      />
      <PatternLines
        data-testid="patternLinesExclusionPeriods"
        height={5}
        id="lines"
        orientation={orientation}
        stroke={theme.palette.text.primary}
        strokeWidth={1}
        width={5}
      />
    </>
  );
};

export default ThresholdWithPatternLines;
