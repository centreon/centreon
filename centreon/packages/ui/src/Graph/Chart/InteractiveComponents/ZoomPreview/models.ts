import type { ScaleTime } from "d3-scale";

import type { RefObject } from "react";
import type { InteractedZone } from "../../models";

export interface ZoomPreviewData extends InteractedZone {
	graphHeight: number;
	graphWidth: number;
	xScale: ScaleTime<number, number>;
	graphSvgRef: RefObject<SVGSVGElement | null>;
	graphMarginLeft: number;
}
