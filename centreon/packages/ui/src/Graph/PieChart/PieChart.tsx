import { ParentSize } from "../..";
import type { PieProps } from "./models";
import ResponsivePie from "./ResponsivePie";

const PieChart = (props: PieProps): JSX.Element => (
	<ParentSize>
		{({ width, height }) => (
			<ResponsivePie {...props} height={height} width={width} />
		)}
	</ParentSize>
);

export default PieChart;
