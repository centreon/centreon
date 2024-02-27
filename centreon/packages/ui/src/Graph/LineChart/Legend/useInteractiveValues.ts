import { useMemo } from 'react';

import { useAtomValue } from 'jotai';
import { equals, find, isNil } from 'ramda';

import { mousePositionAtom } from '../InteractiveComponents/interactionWithGraphAtoms';
import {
  formatMetricValueWithUnit,
  getLineForMetric,
  getMetrics,
  getTimeValue
} from '../../common/timeSeries';
import { Line, TimeValue } from '../../common/timeSeries/models';

import { FormattedMetricData } from './models';

interface InteractiveValues {
  getFormattedValue: (line: Line) => string | null | undefined;
}

interface Props {
  base: number;
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
  xScale;
}

const useInteractiveValues = ({
  timeSeries,
  lines,
  base,
  xScale
}: Props): InteractiveValues => {
  const mousePosition = useAtomValue(mousePositionAtom);

  const timeValue = getTimeValue({
    timeSeries,
    x: mousePosition?.[0],
    xScale
  });

  const graphTimeValue = timeSeries?.find((item) =>
    equals(item.timeTick, timeValue?.timeTick)
  );

  const getMetricsToDisplay = (): Array<number> => {
    if (isNil(graphTimeValue)) {
      return [];
    }
    const metricsData = getMetrics(graphTimeValue as TimeValue);

    const metricsToDisplay = metricsData.filter((metric_id) => {
      const line = getLineForMetric({ lines, metric_id: Number(metric_id) });

      return !isNil(graphTimeValue[metric_id]) && !isNil(line);
    });

    return metricsToDisplay.map(Number);
  };

  const metrics = useMemo(() => getMetricsToDisplay(), [graphTimeValue]);

  const getFormattedMetricData = (
    metric_id: number
  ): FormattedMetricData | null => {
    if (isNil(graphTimeValue)) {
      return null;
    }
    const value = graphTimeValue[metric_id] as number;

    const { color, name, unit } = getLineForMetric({
      lines,
      metric_id
    }) as Line;

    const formattedValue = formatMetricValueWithUnit({
      base,
      unit,
      value
    });

    return {
      color,
      formattedValue,
      name,
      unit
    };
  };

  const getFormattedValue = (line: Line): string | undefined | null => {
    const metric_id = find(equals(line.metric_id), metrics);

    return metric_id ? getFormattedMetricData(metric_id)?.formattedValue : null;
  };

  return { getFormattedValue };
};

export default useInteractiveValues;
