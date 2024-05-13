import { ParentSize } from '../..';

import ResponsivePie from './ResponsivePie';
import { PieProps } from './models';

const PieChart = (props: PieProps): JSX.Element => (
  <ParentSize>
    {({ width, height }) => (
      <ResponsivePie {...props} height={height} width={width} />
    )}
  </ParentSize>
);

export default PieChart;
