import { ScaleLinear } from 'd3-scale';

import { getStackedYScale } from '../../timeSeries';
import { displayArea } from '../../helpers/index';
import { Line, TimeValue } from '../../timeSeries/models';

import { AreaStackedLines } from './models';

interface StackedLinesData {
  displayAreaInvertedStackedLines: boolean;
  displayAreaStackedLines: boolean;
  invertedStackedLines: Array<Line>;
  invertedStackedLinesTimeSeries: Array<TimeValue>;
  regularStackedLines: Array<Line>;
  regularStackedLinesTimeSeries: Array<TimeValue>;
  xScaleStackedLines: ScaleLinear<number, number>;
  yScaleStackedLines: ScaleLinear<number, number>;
}

const useDataStackedLines = (
  areaStackedLines: AreaStackedLines
): StackedLinesData => {
  const { stackedLinesData, invertedStackedLinesData } = areaStackedLines;

  const {
    lines: regularStackedLines,
    timeSeries: regularStackedLinesTimeSeries
  } = stackedLinesData;

  const {
    lines: invertedStackedLines,
    timeSeries: invertedStackedLinesTimeSeries
  } = invertedStackedLinesData;

  const displayAreaStackedLines =
    areaStackedLines.display && displayArea(regularStackedLines);

  const displayAreaInvertedStackedLines =
    areaStackedLines.display && displayArea(invertedStackedLines);

  const stackedYScale = getStackedYScale({
    leftScale: areaStackedLines?.leftScale,
    rightScale: areaStackedLines?.rightScale
  });

  return {
    displayAreaInvertedStackedLines,
    displayAreaStackedLines,
    invertedStackedLines,
    invertedStackedLinesTimeSeries,
    regularStackedLines,
    regularStackedLinesTimeSeries,
    xScaleStackedLines: areaStackedLines?.xScale,
    yScaleStackedLines: stackedYScale
  };
};

export default useDataStackedLines;
