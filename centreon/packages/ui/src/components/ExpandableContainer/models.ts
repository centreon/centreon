import type { SvgIconComponent } from "@mui/icons-material";
import type { CSSProperties, ForwardedRef } from "react";

export interface Parameters {
	toggleExpand: () => void;
	Icon: SvgIconComponent;
	isExpanded: boolean;
	label: string;
	style?: CSSProperties;
	ref: ForwardedRef<HTMLDivElement>;
	key: string;
}
