import { ParentSize } from '../..';

import ResponsiveHeatMap from './ResponsiveHeatMap';
import { HeatMapProps } from './model';

const HeatMap = <TData,>(props: HeatMapProps<TData>): JSX.Element => (
  <ParentSize>
    {({ width }) => <ResponsiveHeatMap<TData> {...props} width={width} />}
  </ParentSize>
);

export default HeatMap;
