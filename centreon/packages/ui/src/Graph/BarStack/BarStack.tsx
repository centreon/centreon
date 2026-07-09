import { ParentSize } from '../..';
import type { BarStackProps } from './models';
import ResponsiveBarStack from './ResponsiveBarStack';

const Bar = (props: BarStackProps): JSX.Element => (
  <ParentSize>
    {({ width, height }) => (
      <ResponsiveBarStack {...props} height={height} width={width} />
    )}
  </ParentSize>
);

export default Bar;
