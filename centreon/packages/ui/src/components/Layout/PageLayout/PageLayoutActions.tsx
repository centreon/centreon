import type { ReactElement, ReactNode } from "react";

import { useStyles } from "./PageLayout.styles";

interface PageLayoutActionsProps {
	children: Array<ReactNode> | ReactNode;
	rowReverse?: boolean;
	className?: string;
}

export const PageLayoutActions = ({
	children,
	rowReverse,
	className,
}: PageLayoutActionsProps): ReactElement => {
	const { classes, cx } = useStyles();

	return (
		<section
			className={cx(classes.pageLayoutActions, className)}
			data-row-reverse={rowReverse}
			id="actions"
		>
			{children}
		</section>
	);
};
