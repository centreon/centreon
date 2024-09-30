import {
  dateTimeFormat,
  getXAxisTickFormat,
  useLocaleDateTimeFormat
} from '@centreon/ui';

import { useTheme } from '@mui/material';

import dayjs from 'dayjs';

import { userAtom } from '@centreon/ui-context';
import { Axis } from '@visx/visx';

import { scaleTime } from '@visx/scale';
import { useAtomValue } from 'jotai';
import { Tooltip } from '../../components';
import type { TimelineProps } from './models';
import { useStyles } from './timeline.styles';
import { useTimeline } from './useTimeline';

interface Props extends TimelineProps {
  width: number;
  height: number;
}

const margin = { top: 10, bottom: 40, left: 15, right: 15 };

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
  const { timezone, locale } = useAtomValue(userAtom);

  const theme = useTheme();

  const xScale = scaleTime({
    domain: [new Date(startDate), new Date(endDate)],
    range: [margin.left, width - margin.right]
  });

  const numTicks = Math.min(Math.ceil(width / 82), 12);

  const { getTimeDifference, formattedData } = useTimeline({ data, locale });

  return (
    <svg width={width} height={height}>
      {formattedData.map(({ start, end, color }) => (
        <Tooltip
          hasCaret
          classes={{
            tooltip: classes.tooltip
          }}
          followCursor={false}
          key={`rect-${start}--${end}`}
          label={
            TooltipContent && (
              <TooltipContent
                start={format({
                  date: dayjs(start).tz(timezone).toDate(),
                  formatString: dateTimeFormat
                })}
                end={format({
                  date: dayjs(end).tz(timezone).toDate(),
                  formatString: dateTimeFormat
                })}
                color={color}
                duration={getTimeDifference(dayjs(start), dayjs(end))}
              />
            )
          }
          position="right"
        >
          <rect
            x={xScale(start)}
            y={margin.top}
            width={xScale(end) - xScale(start)}
            height={height - margin.top - margin.bottom}
            fill={color}
          />
        </Tooltip>
      ))}

      <Axis.AxisBottom
        top={height - margin.bottom + 4}
        scale={xScale}
        numTicks={numTicks}
        tickFormat={(value) => {
          return format({
            date: new Date(value),
            formatString: getXAxisTickFormat({ end: endDate, start: startDate })
          });
        }}
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
