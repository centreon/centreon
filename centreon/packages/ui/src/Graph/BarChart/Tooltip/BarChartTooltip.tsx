import { useAtomValue } from 'jotai';
import { isNil } from 'ramda';

import { Box, Typography } from '@mui/material';

import { TimeValue } from '../../common/timeSeries/models';
import { useLocaleDateTimeFormat } from '../../../utils';
import { formatMetricValueWithUnit } from '../../common/timeSeries';
import { tooltipDataAtom } from '../atoms';

import { useBarChartTooltipStyles } from './useBarChartTooltipStyles';

interface Props {
  base: number;
  timeSeries: Array<TimeValue>;
}

const BarChartTooltip = ({ timeSeries, base }: Props): JSX.Element | null => {
  const { classes } = useBarChartTooltipStyles();
  const { toDate, toTime } = useLocaleDateTimeFormat();
  const tooltipData = useAtomValue(tooltipDataAtom);

  if (isNil(tooltipData)) {
    return null;
  }

  const date = timeSeries[tooltipData.index].timeTick;
  const formattedDateTime = `${toDate(date)} / ${toTime(date)}`;

  return (
    <div className={classes.tooltipContainer}>
      <Typography fontWeight="bold">{formattedDateTime}</Typography>
      <div className={classes.metrics}>
        {tooltipData.data.map(({ metric, value }) => (
          <div
            className={classes.metric}
            data-metric={metric.name}
            key={metric.metric_id}
          >
            <Box
              className={classes.metricColorBox}
              sx={{ backgroundColor: metric.color }}
            />
            <Typography className={classes.metricName}>
              {metric.name}
            </Typography>
            <Typography>
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
