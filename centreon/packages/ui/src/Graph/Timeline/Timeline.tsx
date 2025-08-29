import type { ParentSizeProps } from '@visx/responsive/lib/components/ParentSize';
import { ParentSize } from '../..';

import ResponsiveTimeline from './ResponsiveTimeline';
import type { TimelineProps } from './models';

interface Props extends ParentSizeProps, TimelineProps {}

const Timeline = ({
  data,
  startDate,
  endDate,
  TooltipContent,
  tooltipClassName,
  ...rest
}: Props): JSX.Element => (
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
