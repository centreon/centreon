import type { ScaleLinear } from "d3-scale";
import { equals, reject } from "ramda";

import type { Line, TimeValue } from "../../../../common/timeSeries/models";
import { type GlobalAreaLines, ThresholdType } from "../../../models";

export interface Data {
	lineColor: string;
	metric: string;
	yScale: ScaleLinear<number, number>;
}

export interface Point {
	x: number;
	y: number;
}

export interface ArePointsOnline {
	pointLower: Point;
	pointOrigin: Point;
	pointUpper: Point;
}
export interface Online extends ArePointsOnline {
	maxDistance?: number;
}

export interface FactorsVariation {
	currentFactorMultiplication: number;
	simulatedFactorMultiplication: number;
}

export interface Result {
	getX: (timeValue: TimeValue) => number;
	getY0: (timeValue: TimeValue) => number;
	getY1: (timeValue: TimeValue) => number;
	lineColorY0: string;
	lineColorY1: string;
}

export interface EnvelopeVariationFormula {
	factorsData: FactorsVariation;
	lowerRealValue: number;
	upperRealValue: number;
}

export interface ThresholdLinesModel {
	dataY0: Data;
	dataY1: Data;
	graphHeight: number;
	timeSeries: Array<TimeValue>;
	xScale: ScaleLinear<number, number>;
}

export interface LinesThreshold {
	lineLower: Line;
	lineOrigin: Line;
	lineUpper: Line;
}

export interface WrapperThresholdLinesModel {
	areaThresholdLines?: GlobalAreaLines["areaThresholdLines"];
	lines: Array<Line>;
	xScale: ScaleLinear<number, number>;
	yScalesPerUnit: Record<string, ScaleLinear<number, number>>;
}

export interface ScaleVariationThreshold {
	getY0Variation: (timeValue: TimeValue) => number;
	getY1Variation: (timeValue: TimeValue) => number;
	getYOrigin: (timeValue: TimeValue) => number;
}

export interface Circle extends ScaleVariationThreshold {
	getCountDisplayedCircles?: (value: number) => void;
	getX: (timeValue: TimeValue) => number;
	timeSeries: Array<TimeValue>;
}

export const lowerLineName = "Lower Threshold";
export const upperLineName = "Upper Threshold";

// upper,lower and origin
export const requiredNumberLinesThreshold = 3;

export const findLineOfOriginMetricThreshold = (
	lines: Array<Line>,
): Array<Line> => {
	const metrics = lines.map((line) => {
		const { metric } = line;

		return metric.includes("_upper_thresholds")
			? metric.replace("_upper_thresholds", "")
			: null;
	});

	const originMetric = metrics.find((element) => element);

	return reject((line: Line) => !equals(line.metric, originMetric), lines);
};

export const canDisplayThreshold = (
	areaThresholdLines: GlobalAreaLines["areaThresholdLines"],
): boolean => {
	console.log("->", areaThresholdLines);

	return !!areaThresholdLines?.find(
		(item) => item && item.type in ThresholdType,
	);
};
