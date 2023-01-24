import { curveBasis } from '@visx/curve';
import { PatternLines } from '@visx/pattern';
import { Threshold } from '@visx/threshold';
import { ScaleLinear, ScaleTime } from 'd3-scale';
import { difference, equals, isNil, prop } from 'ramda';

import { useTheme } from '@mui/material/styles';

import { ResourceDetails } from '../../../../Details/models';
import { ExclusionPeriodThresholdData } from '../models';
import { TimeValue } from '../../models';
import {
  getSortedStackedLines,
  getTime,
  getUnits,
  getYScale
} from '../../timeSeries';

import { getNewLinesAnomalyDetection } from './helpers';

interface Props {
  data: ExclusionPeriodThresholdData;
  graphHeight: number;
  leftScale: ScaleLinear<number, number>;
  resource: ResourceDetails;
  rightScale: ScaleLinear<number, number>;
  xScale: ScaleTime<number, number>;
}

const AnomalyDetectionExclusionPeriodThreshold = ({
  data,
  graphHeight,
  resource,
  xScale,
  rightScale,
  leftScale
}: Props): JSX.Element | null => {
  const theme = useTheme();

  const { timeSeries, lines } = data;

  const { newLines } = getNewLinesAnomalyDetection({
    lines,
    resource
  });

  const [secondUnit, thirdUnit] = getUnits(newLines);

  const stackedLines = getSortedStackedLines(newLines);

  const regularLines = difference(newLines, stackedLines);

  const [{ metric: metricY1, unit: unitY1, invert: invertY1 }] =
    regularLines.filter((item) => equals(item.name, 'Upper Threshold'));

  const [{ metric: metricY0, unit: unitY0, invert: invertY0 }] =
    regularLines.filter((item) => equals(item.name, 'Lower Threshold'));

  const y1Scale = getYScale({
    hasMoreThanTwoUnits: !isNil(thirdUnit),
    invert: invertY1,
    leftScale,
    rightScale,
    secondUnit,
    unit: unitY1
  });

  const y0Scale = getYScale({
    hasMoreThanTwoUnits: !isNil(thirdUnit),
    invert: invertY0,
    leftScale,
    rightScale,
    secondUnit,
    unit: unitY0
  });

  const getXPoint = (timeValue: TimeValue): number => {
    return xScale(getTime(timeValue)) as number;
  };
  const getY1Point = (timeValue: TimeValue): number =>
    y1Scale(prop(metricY1, timeValue)) ?? null;
  const getY0Point = (timeValue: TimeValue): number =>
    y0Scale(prop(metricY0, timeValue)) ?? null;

  return (
    <>
      <Threshold
        aboveAreaProps={{
          fill: "url('#lines')",
          fillOpacity: 0.8
        }}
        belowAreaProps={{
          fill: "url('#lines')",
          fillOpacity: 0.8
        }}
        clipAboveTo={0}
        clipBelowTo={graphHeight}
        curve={curveBasis}
        data={timeSeries}
        id={`${getY0Point.toString()}${getY1Point.toString()}`}
        x={getXPoint}
        y0={getY0Point}
        y1={getY1Point}
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

export default AnomalyDetectionExclusionPeriodThreshold;
