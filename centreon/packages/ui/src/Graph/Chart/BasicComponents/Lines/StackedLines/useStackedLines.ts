import { equals, pluck, uniq } from 'ramda';

import {
  getInvertedStackedLines,
  getNotInvertedStackedLines,
  getTimeSeriesForLines
} from '../../../../common/timeSeries';
import { LinesData } from '../models';

interface StackedLinesState {
  invertedStackedLinesData: Record<string, LinesData>;
  stackedLinesData: Record<string, LinesData>;
}

const useStackedLines = ({ lines, timeSeries }): StackedLinesState => {
  const regularStackedLines = getNotInvertedStackedLines(lines);
  const regularStackedUnits = uniq(pluck('unit', regularStackedLines));
  const regularStackedLinesTimeSeriesPerUnit = regularStackedUnits.reduce(
    (acc, stackedUnit) => {
      const relatedLines = regularStackedLines.filter(({ unit }) =>
        equals(unit, stackedUnit)
      );

      return {
        ...acc,
        [stackedUnit]: {
          lines: relatedLines,
          timeSeries: getTimeSeriesForLines({
            lines: relatedLines,
            timeSeries
          })
        }
      };
    },
    {}
  );

  const invertedStackedLines = getInvertedStackedLines(lines);
  const invertedStackedUnits = uniq(pluck('unit', invertedStackedLines));
  const invertedStackedLinesTimeSeriesPerUnit = invertedStackedUnits.reduce(
    (acc, stackedUnit) => {
      const relatedLines = invertedStackedLines.filter(({ unit }) =>
        equals(unit, stackedUnit)
      );

      return {
        ...acc,
        [stackedUnit]: {
          lines: relatedLines,
          timeSeries: getTimeSeriesForLines({
            invert: true,
            lines: relatedLines,
            timeSeries
          })
        }
      };
    },
    {}
  );

  return {
    invertedStackedLinesData: invertedStackedLinesTimeSeriesPerUnit,
    stackedLinesData: regularStackedLinesTimeSeriesPerUnit
  };
};

export default useStackedLines;
