import {
  getInvertedStackedLines,
  getNotInvertedStackedLines,
  getTimeSeriesForLines
} from '../../../timeSeries';
import { LinesData } from '../models';

interface StackedLines {
  invertedStackedLinesData: LinesData;
  stackedLinesData: LinesData;
}

const useStackedLines = ({ lines, timeSeries }): StackedLines => {
  const regularStackedLines = getNotInvertedStackedLines(lines);

  const regularStackedTimeSeries = getTimeSeriesForLines({
    lines: regularStackedLines,
    timeSeries
  });

  const invertedStackedLines = getInvertedStackedLines(lines);
  const invertedStackedTimeSeries = getTimeSeriesForLines({
    lines: invertedStackedLines,
    timeSeries
  });

  return {
    invertedStackedLinesData: {
      lines: invertedStackedLines,
      timeSeries: invertedStackedTimeSeries
    },
    stackedLinesData: {
      lines: regularStackedLines,
      timeSeries: regularStackedTimeSeries
    }
  };
};

export default useStackedLines;
