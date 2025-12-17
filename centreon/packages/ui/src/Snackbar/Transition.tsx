import { Fade } from "@mui/material";
import type { CSSProperties, ReactNode } from "react";
import type {
	TransitionProps as _TransitionProps,
	TransitionActions,
} from "react-transition-group/Transition";

type TransitionHandlerKeys =
	| "onEnter"
	| "onEntering"
	| "onEntered"
	| "onExit"
	| "onExiting"
	| "onExited";
type TransitionKeys =
	| "in"
	| "mountOnEnter"
	| "unmountOnExit"
	| "timeout"
	| "addEndListener"
	| TransitionHandlerKeys;
interface TransitionProps
	extends TransitionActions,
		Partial<Pick<_TransitionProps, TransitionKeys>> {
	style?: CSSProperties;
}

interface Props extends TransitionProps {
	children?: ReactNode;
}

const Transition = ({ children, ...rest }: Props): JSX.Element => (
	<Fade {...rest}>
		<div>{children}</div>
	</Fade>
);

export default Transition;
