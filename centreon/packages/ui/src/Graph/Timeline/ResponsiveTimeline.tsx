import {
  dateTimeFormat,
  getXAxisTickFormat,
  useLocaleDateTimeFormat
} from '@centreon/ui';

import { Typography, useTheme } from '@mui/material';

import dayjs from 'dayjs';

import { userAtom } from '@centreon/ui-context';
import { Axis } from '@visx/visx';

import { scaleTime } from '@visx/scale';
import { BarRounded } from '@visx/shape';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { useCallback } from 'react';
import { Tooltip } from '../../components';
import { margins } from '../common/margins';
import type { TimelineProps } from './models';
import { useStyles } from './timeline.styles';
import { useTimeline } from './useTimeline';

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
  TooltipContent
}: Props) => {
  const { classes } = useStyles();
  const { format } = useLocaleDateTimeFormat();
  const { timezone } = useAtomValue(userAtom);

  const theme = useTheme();

  const xScale = scaleTime({
    domain: [new Date(startDate), new Date(endDate)],
    range: [margins.left, width - margins.right],
    clamp: true
  });

  const numTicks = Math.min(Math.ceil(width / 82), 12);

  const { getTimeDifference } = useTimeline();

  const getFormattedStart = useCallback(
    (start) =>
      format({
        date: dayjs(start).tz(timezone).toDate(),
        formatString: dateTimeFormat
      }),
    [dateTimeFormat, timezone]
  );

  const getFormattedEnd = useCallback(
    (end) =>
      format({
        date: dayjs(end).tz(timezone).toDate(),
        formatString: dateTimeFormat
      }),
    [dateTimeFormat, timezone]
  );

  return (
    <svg width={width} height={height + axisPadding}>
      {data.map(({ start, end, color }, idx) => (
        <Tooltip
          hasCaret
          classes={{
            tooltip: classes.tooltip
          }}
          followCursor={false}
          key={`rect-${start}--${end}`}
          label={
            TooltipContent ? (
              <TooltipContent
                start={getFormattedStart(start)}
                end={getFormattedEnd(end)}
                color={color}
                duration={getTimeDifference({
                  start: dayjs(start),
                  end: dayjs(end)
                })}
              />
            ) : (
              <div style={{ color }}>
                <Typography variant="body2">
                  {getTimeDifference({ start: dayjs(start), end: dayjs(end) })}
                </Typography>
                <Typography variant="body2">{`${format({ date: start, formatString: 'L LT' })} - ${format({ date: end, formatString: 'L LT' })}`}</Typography>
              </div>
            )
          }
          position="top"
        >
          <g>
            <BarRounded
              x={xScale(dayjs(start).tz(timezone))}
              y={0}
              width={
                xScale(dayjs(end).tz(timezone)) -
                xScale(dayjs(start).tz(timezone))
              }
              height={height - margins.bottom}
              fill={color}
              left={equals(idx, 0)}
              radius={4}
              right={equals(idx, data.length - 1)}
            />
          </g>
        </Tooltip>
      ))}

      <Axis.AxisBottom
        top={height - margins.bottom + axisPadding}
        scale={xScale}
        numTicks={numTicks}
        tickFormat={(value) =>
          format({
            date: new Date(value),
            formatString: getXAxisTickFormat({ end: endDate, start: startDate })
          })
        }
        stroke={theme.palette.text.primary}
        tickStroke={theme.palette.text.primary}
        tickLabelProps={() => ({
          fill: theme.palette.text.primary,
          fontSize: theme.typography.caption.fontSize,
          textAnchor: 'middle'
        })}
      />
    </svg>
  );
};

export default Timeline;
