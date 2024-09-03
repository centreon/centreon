import { equals } from "ramda";
import { useState } from "react";
import { makeStyles } from "tss-react/mui";

import type { Theme } from "@mui/material";

import { TimePeriods } from "@centreon/ui";
import type { TabProps } from "..";
import FederatedComponent from "../../../../components/FederatedComponents";
import ExportablePerformanceGraphWithTimeline from "../../../Graph/Performance/ExportableGraphWithTimeline";
import GraphOptions from "../../../Graph/Performance/ExportableGraphWithTimeline/GraphOptions";
import memoizeComponent from "../../../memoizedComponent";
import { ResourceType } from "../../../models";
import ChartGraph from "./ChartGraph";

import HostGraph from "./HostGraph";
import type { GraphTimeParameters } from "./models";

const useStyles = makeStyles()((theme: Theme) => ({
	container: {
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

const GraphTabContent = ({ details }: TabProps): JSX.Element => {
	const { classes } = useStyles();

	const [updatedGraphInterval, setUpdatedGraphInterval] = useState();

	const [graphTimeParameters, setGraphTimeParameters] =
		useState<GraphTimeParameters>();

	const type = details?.type as ResourceType;
	const equalsService = equals(ResourceType.service);
	const equalsMetaService = equals(ResourceType.metaservice);
	const equalsAnomalyDetection = equals(ResourceType.anomalyDetection);

	const isService =
		equalsService(type) ||
		equalsMetaService(type) ||
		equalsAnomalyDetection(type);

	const getTimePeriodsParameters = (data: GraphTimeParameters): void => {
		setGraphTimeParameters(data);
	};

	return (
		<div className={classes.container}>
			{isService ? (
				<>
					<TimePeriods
						adjustTimePeriodData={updatedGraphInterval}
						getParameters={getTimePeriodsParameters}
						renderExternalComponent={<GraphOptions />}
					/>

					<ChartGraph
						resource={details}
						graphInterval={graphTimeParameters}
						updatedGraphInterval={setUpdatedGraphInterval}
					/>
					{graphTimeParameters && (
						<ExportablePerformanceGraphWithTimeline
							interactWithGraph
							graphHeight={280}
							graphTimeParameters={graphTimeParameters}
							renderAdditionalLines={(props): JSX.Element => (
								<FederatedComponent
									{...props}
									path="/anomaly-detection/thresholdLines"
								/>
							)}
							resource={details}
						/>
					)}
				</>
			) : (
				<HostGraph details={details} />
			)}
		</div>
	);
};

const MemoizedGraphTabContent = memoizeComponent<TabProps>({
	Component: GraphTabContent,
	memoProps: ["details", "ariaLabel"],
});

const GraphTab = ({ details }: TabProps): JSX.Element => {
	return <MemoizedGraphTabContent details={details} />;
};

export default GraphTab;
