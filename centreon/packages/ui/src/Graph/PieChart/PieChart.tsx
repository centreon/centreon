import { ParentSize } from '../..';

import ResponsivePie from './ResponsivePie';
import { PieProps } from './models';

const PieChart = (props: PieProps): JSX.Element => (
  <ParentSize>
    {({ width }) => <ResponsivePie {...props} width={width} />}
  </ParentSize>
);

export default PieChart;
