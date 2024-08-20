import { useAtomValue } from 'jotai';
import {
  always,
  cond,
  equals,
  filter,
  gt,
  has,
  isNil,
  path,
  prop,
  reverse,
  sortBy,
  T
} from 'ramda';

import { Box, Typography } from '@mui/material';

import { TimeValue } from '../../common/timeSeries/models';
import { useLocaleDateTimeFormat } from '../../../utils';
import { formatMetricValueWithUnit } from '../../common/timeSeries';
import { tooltipDataAtom } from '../atoms';
import { Tooltip } from '../../LineChart/models';

import { useBarChartTooltipStyles } from './useBarChartTooltipStyles';

interface Props extends Partial<Pick<Tooltip, 'mode' | 'sortOrder'>> {
  base: number;
  timeSeries: Array<TimeValue>;
}

const BarChartTooltip = ({
  timeSeries,
  base,
  mode,
  sortOrder
}: Props): JSX.Element | null => {
  const { classes } = useBarChartTooltipStyles();
  const { toDate, toTime } = useLocaleDateTimeFormat();
  const tooltipData = useAtomValue(tooltipDataAtom);

  if (isNil(tooltipData)) {
    return null;
  }

  if (has('thresholdLabel', tooltipData)) {
    return <Typography>{tooltipData.thresholdLabel}</Typography>;
  }

  const date = timeSeries[tooltipData.index].timeTick;
  const formattedDateTime = `${toDate(date)} / ${toTime(date)}`;

  const isSingleMode = equals(mode, 'single');

  const filteredMetrics = isSingleMode
    ? filter(
        ({ metric }) => equals(metric.metric_id, tooltipData.highlightedMetric),
        tooltipData.data
      )
    : tooltipData.data;

  const displayHighLightedMetric = gt(filteredMetrics.length, 1);

  const sortedMetrics = cond([
    [equals('name'), always(sortBy(path(['metric', 'name']), filteredMetrics))],
    [equals('ascending'), always(sortBy(prop('value'), filteredMetrics))],
    [
      equals('descending'),
      always(reverse(sortBy(prop('value'), filteredMetrics)))
    ],
    [T, always(filteredMetrics)]
  ])(sortOrder);

  return (
    <div className={classes.tooltipContainer}>
      <Typography fontWeight="bold" textAlign="center">
        {formattedDateTime}
      </Typography>
      <div className={classes.metrics}>
        {sortedMetrics.map(({ metric, value }) => (
          <div
            className={classes.metric}
            data-metric={metric.name}
            key={metric.metric_id}
          >
            <Box
              className={classes.metricColorBox}
              sx={{ backgroundColor: metric.color }}
            />
            <Typography
              className={classes.metricName}
              fontWeight={
                displayHighLightedMetric &&
                equals(tooltipData.highlightedMetric, metric.metric_id)
                  ? 'bold'
                  : 'regular'
              }
            >
              {metric.name}
            </Typography>
            <Typography
              fontWeight={
                displayHighLightedMetric &&
                equals(tooltipData.highlightedMetric, metric.metric_id)
                  ? 'bold'
                  : 'regular'
              }
            >
              {formatMetricValueWithUnit({
                base,
                unit: metric.unit,
                value
              })}
            </Typography>
          </div>
        ))}
      </div>
    </div>
  );
};

export default BarChartTooltip;
