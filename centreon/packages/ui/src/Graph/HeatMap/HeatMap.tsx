import { ParentSize } from '../..';

import ResponsiveHeatMap from './ResponsiveHeatMap';
import { HeatMapProps } from './model';

const HeatMap = <TData,>(props: HeatMapProps<TData>): JSX.Element => (
  <ParentSize>
    {({ width, height }) => (
      <ResponsiveHeatMap<TData> {...props} height={height} width={width} />
    )}
  </ParentSize>
);

export default HeatMap;
