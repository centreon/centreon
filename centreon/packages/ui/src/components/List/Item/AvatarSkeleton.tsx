import { Skeleton as MuiSkeleton } from "@mui/material";
import type { ReactElement } from "react";

import { useStyles } from "./ListItem.styles";

export const AvatarSkeleton = (): ReactElement => {
	const { classes } = useStyles();

	return (
		<MuiSkeleton
			animation="wave"
			className={classes.avatar}
			variant="circular"
		/>
	);
};
