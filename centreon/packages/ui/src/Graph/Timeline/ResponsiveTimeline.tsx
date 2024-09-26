import {
  dateTimeFormat,
  getXAxisTickFormat,
  useLocaleDateTimeFormat
} from '@centreon/ui';

import dayjs from 'dayjs';

import { userAtom } from '@centreon/ui-context';
import { AxisBottom } from '@visx/axis';
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

const Timeline = ({
  data,
  start_date,
  end_date,
  width,
  height,
  TooltipContent
}: Props) => {
  const { classes } = useStyles();
  const { format } = useLocaleDateTimeFormat();
  const { timezone, locale } = useAtomValue(userAtom);

  const margin = { top: 10, bottom: 40, left: 15, right: 15 };

  const startDate = new Date(start_date);
  const endDate = new Date(end_date);

  const xScale = scaleTime({
    domain: [startDate, endDate],
    range: [margin.left, width - margin.right]
  });

  const numTicks = Math.min(Math.ceil(width / 82), 12);

  const { getTimeDifference } = useTimeline({ locale });

  return (
    <svg width={width} height={height}>
      {data.map((d, i) => (
        <Tooltip
          hasCaret
          classes={{
            tooltip: classes.tooltip
          }}
          followCursor={false}
          key={`rect-${i}`}
          label={
            TooltipContent && (
              <TooltipContent
                start={format({
                  date: dayjs(d.start).tz(timezone).toDate(),
                  formatString: dateTimeFormat
                })}
                end={format({
                  date: dayjs(d.end).tz(timezone).toDate(),
                  formatString: dateTimeFormat
                })}
                color={d.color}
                duration={getTimeDifference(dayjs(d.start), dayjs(d.end))}
              />
            )
          }
          position="right"
        >
          <rect
            x={xScale(d.start)}
            y={margin.top}
            width={xScale(d.end) - xScale(d.start)}
            height={height - margin.top - margin.bottom}
            fill={d.color}
          />
        </Tooltip>
      ))}

      <AxisBottom
        top={height - margin.bottom}
        scale={xScale}
        numTicks={numTicks}
        tickFormat={(value) => {
          return format({
            date: new Date(value),
            formatString: getXAxisTickFormat({ end: end_date, start: start_date })
          });
        }}
        stroke="black"
        tickStroke="black"
        tickLabelProps={() => ({
          fill: 'black',
          fontSize: 10,
          textAnchor: 'middle'
        })}
      />
    </svg>
  );
};

export default Timeline;
