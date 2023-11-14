import { Dispatch, SetStateAction, useEffect } from 'react';

import {
  equals,
  find,
  head,
  isEmpty,
  isNil,
  map,
  pipe,
  propEq,
  reject
} from 'ramda';

import { Line } from '../../common/timeSeries/models';

interface LegendActions {
  clearHighlight: () => void;
  highlightLine: (metric_id: number) => void;
  selectMetricLine: (metric_id: number) => void;
  toggleMetricLine: (metric_id: number) => void;
}

interface Props {
  lines: Array<Line>;
  setLinesGraph: Dispatch<SetStateAction<Array<Line> | null>>;
}

const useLegend = ({ lines, setLinesGraph }: Props): LegendActions => {
  const displayedLines = reject(propEq(false, 'display'), lines);
  const getLineByMetric = (metric_id: number): Line =>
    find(propEq(metric_id, 'metric_id'), lines) as Line;

  const toggleMetricLine = (metric_id): void => {
    const line = getLineByMetric(metric_id);

    setLinesGraph([
      ...reject(propEq(metric_id, 'metric_id'), lines),
      { ...line, display: !line.display }
    ]);
  };

  const highlightLine = (metric_id): void => {
    const fadedLines = map((line) => ({ ...line, highlight: false }), lines);
    const data = [
      ...reject(propEq(metric_id, 'metric_id'), fadedLines),
      { ...getLineByMetric(metric_id), highlight: true }
    ];

    setLinesGraph(data);
  };

  const clearHighlight = (): void => {
    setLinesGraph(map((line) => ({ ...line, highlight: undefined }), lines));
  };

  const selectMetricLine = (metric_id: number): void => {
    const metricLine = getLineByMetric(metric_id);

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
      display: find(propEq(line.metric_id, 'metric_id'), lines)?.display ?? true
    }));

    setLinesGraph(newLines);
  }, [lines]);

  return { clearHighlight, highlightLine, selectMetricLine, toggleMetricLine };
};

export default useLegend;
