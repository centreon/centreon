import { ParentSize } from '../..';

import ResponsivePie from './ResponsivePie';
import { PieProps } from './models';

const Pie = (props: PieProps): JSX.Element => (
  <ParentSize>
    {({ width }) => <ResponsivePie {...props} width={width} />}
  </ParentSize>
);

export default Pie;
