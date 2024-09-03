import { makeStyles } from "tss-react/mui";

export const useChartGraphStyles = makeStyles()((theme) => ({
	container: {
		overflow: "visible",
		backgroundColor: theme.palette.background.paper,
	},
	commentContainer: {
		padding: theme.spacing(1),
		backgroundColor: theme.palette.background.default,
		justifyContent: "center",
		display: "flex",
		flexDirection: "column",
	},
	graphTabContainer: {
		display: "grid",
		gridRowGap: theme.spacing(2),
		gridTemplateRows: "auto 1fr",
	},
	exportToPngButton: {
		display: "flex",
		justifyContent: "space-between",
		margin: theme.spacing(0, 1, 1, 2),
	},
	graph: {
		height: "100%",
		margin: "auto",
		width: "100%",
	},
	graphContainer: {
		display: "grid",
		gridTemplateRows: "1fr",
		padding: theme.spacing(2, 1, 1),
	},
}));
