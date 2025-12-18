import type { ParentSizeProps } from '@visx/responsive/lib/components/ParentSize';

import { ParentSize } from '../..';
import type { TimelineProps } from './models';
import ResponsiveTimeline from './ResponsiveTimeline';

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
        endDate={endDate}
        height={height}
        startDate={startDate}
        TooltipContent={TooltipContent}
        tooltipClassName={tooltipClassName}
        width={width}
      />
    )}
  </ParentSize>
);
export default Timeline;
