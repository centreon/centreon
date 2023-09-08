import { Dispatch, SetStateAction, useEffect } from 'react';

import {
  equals,
  find,
  head,
  isEmpty,
  isNil,
  map,
  pipe,
  prop,
  propEq,
  reject,
  sortBy,
  compose,
  toLower
} from 'ramda';

import { Line } from '../../common/timeSeries/models';

interface LegendActions {
  clearHighlight: () => void;
  highlightLine: (metric: string) => void;
  selectMetricLine: (metric: string) => void;
  toggleMetricLine: (metric: string) => void;
}

interface Props {
  lines: Array<Line>;
  setLinesGraph: Dispatch<SetStateAction<Array<Line> | null>>;
}

const useLegend = ({ lines, setLinesGraph }: Props): LegendActions => {
  const displayedLines = reject(propEq('display', false), lines);
  const getLineByMetric = (metric: string): Line =>
    find(propEq('metric', metric), lines) as Line;

  const toggleMetricLine = (metric): void => {
    const line = getLineByMetric(metric);

    setLinesGraph([
      ...reject(propEq('metric', metric), lines),
      { ...line, display: !line.display }
    ]);
  };

  const highlightLine = (metric): void => {
    const fadedLines = map((line) => ({ ...line, highlight: false }), lines);
    const data = [
      ...reject(propEq('metric', metric), fadedLines),
      { ...getLineByMetric(metric), highlight: true }
    ];

    const sortedData = sortBy(compose(toLower, prop('name')), data);

    setLinesGraph(sortedData);
  };

  const clearHighlight = (): void => {
    setLinesGraph(map((line) => ({ ...line, highlight: undefined }), lines));
  };

  const selectMetricLine = (metric: string): void => {
    const metricLine = getLineByMetric(metric);

    const isLineDisplayed = pipe(head, equals(metricLine))(displayedLines);
    const isOnlyLineDisplayed =
      equals(displayedLines.length, 1) && isLineDisplayed;

    if (isOnlyLineDisplayed || isEmpty(displayedLines)) {
      setLinesGraph(
        map(
          (line) => ({
            ...line,
            display: true
          }),
          lines
        )
      );

      return;
    }

    setLinesGraph(
      map(
        (line) => ({
          ...line,
          display: equals(line, metricLine)
        }),
        lines
      )
    );
  };

  useEffect(() => {
    if (isNil(lines) || isEmpty(lines)) {
      return;
    }

    const newLines = lines.map((line) => ({
      ...line,
      display: find(propEq('name', line.name), lines)?.display ?? true
    }));

    setLinesGraph(newLines);
  }, [lines]);

  return { clearHighlight, highlightLine, selectMetricLine, toggleMetricLine };
};

export default useLegend;
