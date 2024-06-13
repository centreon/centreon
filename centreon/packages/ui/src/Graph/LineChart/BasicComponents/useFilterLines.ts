import { useMemo } from 'react';

import { equals, propEq, reject } from 'ramda';

import { Line } from '../../common/timeSeries/models';

import {
  findLineOfOriginMetricThreshold,
  lowerLineName,
  upperLineName
} from './Lines/Threshold/models';

interface UseFilterLines {
  displayThreshold?: boolean;
  lines: Array<Line>;
  linesGraph: Array<Line> | null;
}

interface Result {
  displayedLines: Array<Line>;
  newLines: Array<Line>;
}

const useFilterLines = ({
  displayThreshold = false,
  lines,
  linesGraph
}: UseFilterLines): Result => {
  const filterLines = (): Array<Line> => {
    const lineOriginMetric = findLineOfOriginMetricThreshold(lines);

    const findLinesUpperLower = lines.map((line) =>
      equals(line.name, lowerLineName) || equals(line.name, upperLineName)
        ? line
        : null
    );

    const linesUpperLower = reject((element) => !element, findLinesUpperLower);

    return [...lineOriginMetric, ...linesUpperLower] as Array<Line>;
  };

  const newLines = useMemo(() => {
    if (!lines || !displayThreshold) {
      return lines;
    }

    return filterLines();
  }, [lines, displayThreshold]);

  const newDisplayedLines = linesGraph ?? newLines;

  const displayedLines = reject(propEq(false, 'display'), newDisplayedLines);

  return { displayedLines, newLines: linesGraph ?? newLines };
};

export default useFilterLines;
