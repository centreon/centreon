import {
	type Interval,
	LineChart,
	type LineChartData,
	type TooltipData,
	useFetchQuery,
} from "@centreon/ui";
import { path } from "ramda";
import { useRef, useState } from "react";
import MemoizedGraphActions from "../../../Graph/Performance/GraphActions";
import Comment from "./Comment";
import { useChartGraphStyles } from "./chartGraph.styles";
import useRetrieveTimeLine from "./useRetrieveTimeLine";
const ChartGraph = ({ graphInterval, resource, updatedGraphInterval }) => {
	const { classes } = useChartGraphStyles();
	const [graphRef, setGraphRef] = useState();

	const ref = useRef();

	const graphEndpoint = path<string>(
		["links", "endpoints", "performance_graph"],
		resource,
	);

	const { data } = useFetchQuery<LineChartData>({
		getEndpoint: () =>
			`${graphEndpoint}?start=${graphInterval?.start}&end=${graphInterval?.end}`,
		getQueryKey: () => [
			"graphPerformance",
			graphInterval?.start,
			graphInterval?.end,
			graphEndpoint,
		],
		queryOptions: {
			enabled: !!graphInterval && !!graphEndpoint,
			suspense: false,
		},
	});

	const timelineEndpoint = path<string>(
		["links", "endpoints", "timeline"],
		resource,
	);

	const timeLineData = useRetrieveTimeLine({
		timelineEndpoint,
		start: graphInterval?.start,
		end: graphInterval?.end,
		timelineEventsLimit: graphInterval?.timelineEventsLimit,
	});

	const getInterval = (interval: Interval): void => {
		updatedGraphInterval(interval);
	};

	const graphActions = graphInterval && (
		<MemoizedGraphActions
			end={graphInterval.end}
			performanceGraphRef={graphRef}
			resource={resource}
			start={graphInterval.start}
			timeline={[]}
		/>
	);

	const getRef = (ref) => {
		setGraphRef(ref);
	};

	console.log({ timeLineData });

	return (
		<LineChart
			// a regler with displayEvent notation
			annotationEvent={{ data: timeLineData ? timeLineData.result : [] }}
			containerStyle={classes.container}
			getRef={getRef}
			ref={ref}
			data={data}
			end={graphInterval?.end}
			height={280}
			legend={{ mode: "grid", placement: "bottom" }}
			lineStyle={{ lineWidth: 1 }}
			header={{ extraComponent: graphActions }}
			tooltip={{
				// mode: "all",
				// sortOrder: "name",
				enable: true,
				renderComponent: ({
					data,
					tooltipOpen,
					hideTooltip,
				}: TooltipData): JSX.Element => {
					console.log({ data });
					// return <div> hola</div>;
					return (
						<Comment
							commentDate={data}
							hideAddCommentTooltip={hideTooltip}
							resource={resource}
						/>
					);
				},
			}}
			// shapeLines={{
			//     areaThresholdLines: getShapeLines,
			// }}
			start={graphInterval?.start}
			timeShiftZones={{ enable: true, getInterval }}
			zoomPreview={{ enable: true, getInterval }}
		/>
	);
};

export default ChartGraph;
