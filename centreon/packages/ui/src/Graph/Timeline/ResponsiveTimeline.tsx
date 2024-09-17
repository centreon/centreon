import { getXAxisTickFormat, useLocaleDateTimeFormat } from '@centreon/ui';
import { AxisBottom } from '@visx/axis';
import { scaleTime } from '@visx/scale';
import type { TimelineProps } from './models';

interface Props extends TimelineProps {
  width : number; 
  height : number;
}

const Timeline = ({ data, start_date, end_date, width, height }: Props) => {
  const { format } = useLocaleDateTimeFormat();

  const margin = { top: 10, bottom: 40, left: 15, right: 15 };

  const startDate = new Date(start_date);
  const endDate = new Date(end_date);

  const xScale = scaleTime({
    domain: [startDate, endDate],
    range: [margin.left, width - margin.right]
  });

  const numTicks = Math.min(Math.ceil(width / 82), 12);

  return (
    <svg width={width} height={height}>
      {data.map((d, i) => (
        <rect
          key={`rect-${i}`}
          x={xScale(d.start)}
          y={margin.top}
          width={xScale(d.end) - xScale(d.start)}
          height={height - margin.top - margin.bottom}
          fill={d.color}
        />
      ))}

      <AxisBottom
        top={height - margin.bottom}
        scale={xScale}
        numTicks={ numTicks}
        tickFormat={(value) => {
          return format({ date: new Date(value), formatString: getXAxisTickFormat({ end : endDate, start : startDate }) }) ; 
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
