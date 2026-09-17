import {
  getInvertedStackedLines,
  getNotInvertedStackedLines,
  getStackedLinesTimeSeriesPerStackAndUnit
} from '../../../../common/timeSeries';
import type { Line, TimeValue } from '../../../../common/timeSeries/models';
import type { LinesData } from '../models';

interface StackedLinesState {
  invertedStackedLinesData: Record<string, LinesData>;
  stackedLinesData: Record<string, LinesData>;
}

interface UseStackedLinesProps {
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
}

const useStackedLines = ({
  lines,
  timeSeries
}: UseStackedLinesProps): StackedLinesState => {
  const regularStackedLines = getNotInvertedStackedLines(lines);
  const {
    stackedLinesTimeSeriesPerStackKeyAndUnit:
      regularStackedLinesTimeSeriesPerStackKeyAndUnit
  } = getStackedLinesTimeSeriesPerStackAndUnit({
    stackedLines: regularStackedLines,
    timeSeries
  });

  const invertedStackedLines = getInvertedStackedLines(lines);

  const {
    stackedLinesTimeSeriesPerStackKeyAndUnit:
      invertedStackedLinesTimeSeriesPerStackKeyAndUnit
  } = getStackedLinesTimeSeriesPerStackAndUnit({
    invert: true,
    stackedLines: invertedStackedLines,
    timeSeries
  });

  return {
    invertedStackedLinesData: invertedStackedLinesTimeSeriesPerStackKeyAndUnit,
    stackedLinesData: regularStackedLinesTimeSeriesPerStackKeyAndUnit
  };
};

export default useStackedLines;
