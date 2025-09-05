import { ParentSize } from '../..';

import ResponsiveTimeline from './ResponsiveTimeline';

const Timeline = ({
  data,
  startDate,
  endDate,
  TooltipContent,
  tooltipClassName,
  ...rest
}): JSX.Element => (
  <ParentSize {...rest}>
    {({ width, height }) => (
      <ResponsiveTimeline
        data={data}
        startDate={startDate}
        endDate={endDate}
        TooltipContent={TooltipContent}
        tooltipClassName={tooltipClassName}
        height={height}
        width={width}
      />
    )}
  </ParentSize>
);

export default Timeline;
