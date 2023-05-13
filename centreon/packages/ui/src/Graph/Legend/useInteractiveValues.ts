import { equals, find, isNil } from 'ramda';
import { useAtomValue } from 'jotai';

import { TimeValue, Line } from '../timeSeries/models';
import { timeValueAtom } from '../InteractiveComponents/interactionWithGraphAtoms';
import { getMetrics, getLineForMetric, formatMetricValue } from '../timeSeries';

import { FormattedMetricData } from './models';

interface InteractiveValues {
  getFormattedValue: (line: Line) => string | null | undefined;
}

interface Props {
  base: number;
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
}

const useInteractiveValues = ({
  timeSeries,
  lines,
  base
}: Props): InteractiveValues => {
  const timeValue = useAtomValue(timeValueAtom);

  const graphTimeValue = timeSeries.find((item) =>
    equals(item.timeTick, timeValue?.timeTick)
  );

  const getMetricsToDisplay = (): Array<string> => {
    if (isNil(graphTimeValue)) {
      return [];
    }
    const metrics = getMetrics(graphTimeValue as TimeValue);

    const metricsToDisplay = metrics.filter((metric) => {
      const line = getLineForMetric({ lines, metric });

      return !isNil(graphTimeValue[metric]) && !isNil(line);
    });

    return metricsToDisplay;
  };

  const metrics = getMetricsToDisplay();

  const getFormattedMetricData = (
    metric: string
  ): FormattedMetricData | null => {
    if (isNil(graphTimeValue)) {
      return null;
    }
    const value = graphTimeValue[metric] as number;

    const { color, name, unit } = getLineForMetric({
      lines,
      metric
    }) as Line;

    const formattedValue = formatMetricValue({
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
    const metric = find(equals(line.metric), metrics);

    return metric && getFormattedMetricData(metric)?.formattedValue;
  };

  return { getFormattedValue };
};

export default useInteractiveValues;
