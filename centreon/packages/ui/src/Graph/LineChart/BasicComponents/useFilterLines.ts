import { Dispatch, SetStateAction, useEffect } from 'react';

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
  setLinesGraph: Dispatch<SetStateAction<Array<Line> | null>>;
}

interface Result {
  displayedLines: Array<Line>;
  newLines: Array<Line>;
}

const useFilterLines = ({
  displayThreshold = false,
  lines,
  linesGraph,
  setLinesGraph
}: UseFilterLines): Result => {
  const displayedLines = reject(propEq(false, 'display'), linesGraph ?? lines);
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

  useEffect(() => {
    const filteredLines = filterLines();
    if (!lines || !displayThreshold) {
      setLinesGraph(lines);

      return;
    }

    setLinesGraph(filteredLines);
  }, [lines, displayThreshold]);

  return { displayedLines, newLines: linesGraph ?? lines };
};

export default useFilterLines;
