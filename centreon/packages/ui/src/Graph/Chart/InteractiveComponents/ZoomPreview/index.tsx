import { alpha, useTheme } from "@mui/system";
import { omit } from "ramda";
import { margin } from "../../common";
import Bar from "../Bar";
import type { ZoomPreviewData } from "./models";
import useZoomPreview from "./useZoomPreview";

const ZoomPreview = (data: ZoomPreviewData): JSX.Element => {
	const theme = useTheme();

	const {
		graphHeight,
		xScale,
		graphWidth,
		getInterval,
		graphSvgRef,
		graphMarginLeft,
		...rest
	} = data;

	const { zoomBarWidth, zoomBoundaries } = useZoomPreview({
		getInterval,
		graphWidth,
		xScale,
		graphSvgRef,
		graphMarginLeft,
	});

	const restData = omit(["enable"], { ...rest });

	return (
		<g>
			<Bar
				fill={alpha(theme.palette.primary.main, 0.2)}
				height={graphHeight - margin.bottom}
				stroke={alpha(theme.palette.primary.main, 0.5)}
				width={zoomBarWidth}
				x={zoomBoundaries?.start || 0}
				y={0}
				{...restData}
			/>
		</g>
	);
};

export default ZoomPreview;
