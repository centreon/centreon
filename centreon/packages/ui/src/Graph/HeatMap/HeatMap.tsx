import { ParentSize } from "../..";
import type { HeatMapProps } from "./model";
import ResponsiveHeatMap from "./ResponsiveHeatMap";

const HeatMap = <TData,>(props: HeatMapProps<TData>): JSX.Element => (
	<ParentSize>
		{({ width, height }) => (
			<ResponsiveHeatMap<TData> {...props} height={height} width={width} />
		)}
	</ParentSize>
);

export default HeatMap;
