import { scaleBand, scaleOrdinal } from "@visx/scale";
import { BarGroupHorizontal, BarGroup as VisxBarGroup } from "@visx/shape";
import type { ScaleLinear } from "d3-scale";
import { difference, equals, keys, omit, pick } from "ramda";
import { memo, useMemo } from "react";

import { useDeepMemo } from "../../utils";
import {
	getSortedStackedLines,
	getStackedLinesTimeSeriesPerStackAndUnit,
	getTime,
	getTimeSeriesForLines,
	getUnits,
} from "../common/timeSeries";
import type { Line, TimeValue } from "../common/timeSeries/models";
import MemoizedGroup from "./MemoizedGroup";
import type { BarStyle } from "./models";

// Minimum value for logarithmic scale to avoid log(0)
const minLogScaleValue = 0.001;

const getNeutralValue = (scaleType?: "linear" | "logarithmic") =>
	equals(scaleType, "logarithmic") ? minLogScaleValue : 0;

interface Props {
	barStyle: BarStyle;
	isTooltipHidden: boolean;
	lines: Array<Line>;
	orientation: "horizontal" | "vertical";
	size: number;
	timeSeries: Array<TimeValue>;
	xScale;
	yScalesPerUnit: Record<string, ScaleLinear<number, number>>;
	scaleType?: "linear" | "logarithmic";
}

const BarGroup = ({
	orientation,
	timeSeries,
	size,
	lines,
	xScale,
	yScalesPerUnit,
	isTooltipHidden,
	barStyle,
	scaleType,
}: Props): JSX.Element => {
	const isHorizontal = equals(orientation, "horizontal");

	const [firstUnit] = getUnits(lines);

	const BarComponent = useMemo(
		() => (isHorizontal ? VisxBarGroup : BarGroupHorizontal),
		[isHorizontal],
	);

	const stackedLines = getSortedStackedLines(lines);
	const notStackedLines = difference(lines, stackedLines);
	const notStackedTimeSeries = getTimeSeriesForLines({
		lines: notStackedLines,
		timeSeries,
	});

	const { stackedLinesTimeSeriesPerStackKeyAndUnit, stackedKeys } = useMemo(
		() =>
			getStackedLinesTimeSeriesPerStackAndUnit({ stackedLines, timeSeries }),
		[stackedLines, timeSeries],
	);

	const normalizedTimeSeries = notStackedTimeSeries.map((timeSerie) => ({
		...timeSerie,
		...stackedKeys,
	}));

	const lineKeys = useDeepMemo({
		deps: [normalizedTimeSeries],
		variable: keys(omit(["timeTick"], normalizedTimeSeries[0])),
	});
	const sortedLineKeys = lineKeys.sort((lineKeyA: string, lineKeyB: string) => {
		if (lineKeyA.startsWith("stacked-") && !lineKeyB.startsWith("stacked-")) {
			return true;
		}

		const lineKeysA = lineKeyA.split("-");
		const lineKeysB = lineKeyB.split("-");

		return lineKeysA[2] === "" && lineKeysB[2] !== "";
	});
	const colors = useDeepMemo({
		deps: [lineKeys, lines],
		variable: lineKeys.map((key) => {
			const metric = lines.find(({ metric_id }) =>
				equals(metric_id, Number(key)),
			);

			return metric?.lineColor || "";
		}),
	});

	const colorScale = useMemo(
		() =>
			scaleOrdinal<number, string>({
				domain: lineKeys,
				range: colors,
			}),
		[...lineKeys, ...colors, colors, lineKeys],
	);
	const metricScale = useMemo(
		() =>
			scaleBand({
				domain: lineKeys,
				padding: 0.1,
				range: [0, xScale.bandwidth()],
			}),
		[lineKeys, xScale.bandwidth],
	);

	const placeholderScale = yScalesPerUnit[firstUnit];

	const barComponentBaseProps = useMemo(
		() =>
			isHorizontal
				? {
						x0: getTime,
						x0Scale: xScale,
						x1Scale: metricScale,
						yScale: placeholderScale,
					}
				: {
						xScale: placeholderScale,
						y0: getTime,
						y0Scale: xScale,
						y1Scale: metricScale,
					},
		[isHorizontal, placeholderScale, xScale, metricScale],
	);

	const neutralValue = useMemo(() => getNeutralValue(scaleType), [scaleType]);

	return (
		<BarComponent<TimeValue>
			color={colorScale}
			data={normalizedTimeSeries}
			height={size}
			keys={sortedLineKeys}
			{...barComponentBaseProps}
		>
			{(barGroups) =>
				barGroups.map((barGroup, index) => {
					return (
						<MemoizedGroup
							key={`bar-group-${barGroup.index}-${barGroup.x0}`}
							barGroup={barGroup}
							barStyle={barStyle}
							stackedLinesTimeSeriesPerStackKeyAndUnit={
								stackedLinesTimeSeriesPerStackKeyAndUnit
							}
							notStackedTimeSeries={notStackedTimeSeries}
							notStackedLines={notStackedLines}
							isTooltipHidden={isTooltipHidden}
							isHorizontal={isHorizontal}
							neutralValue={neutralValue}
							yScalesPerUnit={yScalesPerUnit}
							barIndex={index}
						/>
					);
				})
			}
		</BarComponent>
	);
};

const propsToMemoize = [
	"orientation",
	"timeSeries",
	"size",
	"lines",
	"secondUnit",
	"isCenteredZero",
	"barStyle",
	"scaleType",
];

export default memo(BarGroup, (prevProps, nextProps) => {
	const prevYScale = prevProps.yScalesPerUnit;
	const prevXScale = [
		...prevProps.xScale.domain(),
		...prevProps.xScale.range(),
	];

	const nextYScale = nextProps.yScalesPerUnit;
	const nextXScale = [
		...nextProps.xScale.domain(),
		...nextProps.xScale.range(),
	];

	return (
		equals(pick(propsToMemoize, prevProps), pick(propsToMemoize, nextProps)) &&
		equals(prevYScale, nextYScale) &&
		equals(prevXScale, nextXScale)
	);
});
