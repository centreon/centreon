import { ParentSize } from '../..';

import ResponsiveTimeline from './ResponsiveTimeline';
import type { TimelineProps } from './models';

const Timeline = (props: TimelineProps): JSX.Element => (
  <ParentSize>
    {({ width, height }) => (
      <ResponsiveTimeline {...props} height={height} width={width} />
    )}
  </ParentSize>
);

export default Timeline;
