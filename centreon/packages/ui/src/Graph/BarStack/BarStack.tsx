import { ParentSize } from '../..';

import ResponsiveBarStack from './ResponsiveBarStack';
import { BarStackProps } from './models';

const Bar = (props: BarStackProps): JSX.Element => (
  <ParentSize>
    {({ width, height }) => <ResponsiveBarStack {...props} height={height} />}
  </ParentSize>
);

export default Bar;
