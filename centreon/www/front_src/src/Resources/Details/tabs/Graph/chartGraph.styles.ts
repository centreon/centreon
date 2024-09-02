import { makeStyles } from "tss-react/mui";

export const useChartGraphStyles = makeStyles()((theme) => ({
	container: {
		overflow: "visible",
		backgroundColor: theme.palette.background.paper,
	},
}));
