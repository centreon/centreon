import {
  getInvertedStackedLines,
  getNotInvertedStackedLines,
  getTimeSeriesForLines
} from '../../timeSeries';
import { LinesData } from '../models';

interface StackedLines {
  invertedStackedLines: LinesData;
  regularStackedLines: LinesData;
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
    invertedStackedLines: {
      lines: invertedStackedLines,
      timeSeries: invertedStackedTimeSeries
    },
    regularStackedLines: {
      lines: regularStackedLines,
      timeSeries: regularStackedTimeSeries
    }
  };
};

export default useStackedLines;
