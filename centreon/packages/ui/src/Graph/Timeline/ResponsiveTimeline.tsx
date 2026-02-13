import { Typography, useTheme } from '@mui/material';

import {
  dateTimeFormat,
  getXAxisTickFormat,
  useLocaleDateTimeFormat
} from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { scaleTime } from '@visx/scale';
import { BarRounded } from '@visx/shape';
import { Axis } from '@visx/visx';
import dayjs from 'dayjs';
import timezonePlugin from 'dayjs/plugin/timezone';
import utc from 'dayjs/plugin/utc';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { useCallback } from 'react';

import { Tooltip } from '../../components';
import { margins } from '../common/margins';
import type { TimelineProps } from './models';
import { useStyles } from './timeline.styles';
import { useTimeline } from './useTimeline';

dayjs.extend(utc);
dayjs.extend(timezonePlugin);
interface Props extends TimelineProps {
  width: number;
  height: number;
}

const axisPadding = 4;

const Timeline = ({
  data,
  startDate,
  endDate,
  width,
  height,
  TooltipContent,
  tooltipClassName
}: Props) => {
  const { classes, cx } = useStyles();
  const { format } = useLocaleDateTimeFormat();
  const { timezone } = useAtomValue(userAtom);

  const theme = useTheme();

  const xScale = scaleTime({
    clamp: true,
    domain: [new Date(startDate), new Date(endDate)],
    range: [margins.left, width - margins.right]
  });

  const numTicks = Math.min(Math.ceil(width / 82), 12);

  const { getTimeDifference } = useTimeline();

  const getFormattedStart = useCallback(
    (start) =>
      format({
        date: dayjs(start).tz(timezone).toDate(),
        formatString: dateTimeFormat
      }),
    [timezone, format]
  );

  const getFormattedEnd = useCallback(
    (end) =>
      format({
        date: dayjs(end).tz(timezone).toDate(),
        formatString: dateTimeFormat
      }),
    [timezone, format]
  );

  return (
    <svg height={height + axisPadding} width={width}>
      <title>timeline</title>
      {data.map(({ start, end, color }, idx) => (
        <Tooltip
          classes={{
            tooltip: cx(classes.tooltip, tooltipClassName)
          }}
          followCursor={false}
          hasCaret
          key={`rect-${start}--${end}`}
          label={
            TooltipContent ? (
              <TooltipContent
                color={color}
                duration={getTimeDifference({
                  end: dayjs(end),
                  start: dayjs(start)
                })}
                end={getFormattedEnd(end)}
                start={getFormattedStart(start)}
              />
            ) : (
              <div style={{ color }}>
                <Typography variant="body2">
                  {getTimeDifference({ end: dayjs(end), start: dayjs(start) })}
                </Typography>
                <Typography variant="body2">{`${format({ date: start, formatString: 'L LT' })} - ${format({ date: end, formatString: 'L LT' })}`}</Typography>
              </div>
            )
          }
          position="top"
        >
          <g>
            <BarRounded
              fill={color}
              height={height - margins.bottom}
              left={equals(idx, 0)}
              radius={4}
              right={equals(idx, data.length - 1)}
              width={
                xScale(dayjs(end).tz(timezone)) -
                xScale(dayjs(start).tz(timezone))
              }
              x={xScale(dayjs(start).tz(timezone))}
              y={0}
            />
          </g>
        </Tooltip>
      ))}

      <Axis.AxisBottom
        numTicks={numTicks}
        scale={xScale}
        stroke={theme.palette.text.primary}
        tickFormat={(value) =>
          format({
            date: new Date(value),
            formatString: getXAxisTickFormat({
              end: endDate,
              start: startDate
            })
          })
        }
        tickLabelProps={() => ({
          fill: theme.palette.text.primary,
          fontSize: theme.typography.caption.fontSize,
          textAnchor: 'middle'
        })}
        tickStroke={theme.palette.text.primary}
        top={height - margins.bottom + axisPadding}
      />
    </svg>
  );
};

export default Timeline;
