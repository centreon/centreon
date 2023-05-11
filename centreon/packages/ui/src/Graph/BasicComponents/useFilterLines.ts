import { useEffect } from 'react';

import { useAtom } from 'jotai';
import { equals, propEq, reject } from 'ramda';

import { linesGraphAtom } from '../graphAtoms';
import {
  findLineOfOriginMetricThreshold,
  lowerLineName,
  upperLineName
} from '../helpers';
import { Line } from '../timeSeries/models';

interface UseFilterLines {
  displayThreshold?: boolean;
  lines: Array<Line>;
}

interface Result {
  displayedLines: Array<Line>;
  newLines: Array<Line>;
}

const useFilterLines = ({
  displayThreshold = false,
  lines
}: UseFilterLines): Result => {
  const [linesGraph, setLinesGraph] = useAtom(linesGraphAtom);

  const displayedLines = reject(propEq('display', false), linesGraph ?? lines);
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
    if (!lines || !displayThreshold || filteredLines.length <= 0) {
      return;
    }

    setLinesGraph(filterLines());
  }, [lines, displayThreshold]);

  return { displayedLines, newLines: linesGraph ?? lines };
};

export default useFilterLines;
