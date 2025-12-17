import {
	getInvertedStackedLines,
	getNotInvertedStackedLines,
	getStackedLinesTimeSeriesPerStackAndUnit,
} from "../../../../common/timeSeries";
import type { LinesData } from "../models";

interface StackedLinesState {
	invertedStackedLinesData: Record<string, LinesData>;
	stackedLinesData: Record<string, LinesData>;
}

const useStackedLines = ({ lines, timeSeries }): StackedLinesState => {
	const regularStackedLines = getNotInvertedStackedLines(lines);
	const {
		stackedLinesTimeSeriesPerStackKeyAndUnit:
			regularStackedLinesTimeSeriesPerStackKeyAndUnit,
	} = getStackedLinesTimeSeriesPerStackAndUnit({
		stackedLines: regularStackedLines,
		timeSeries,
	});

	const invertedStackedLines = getInvertedStackedLines(lines);

	const {
		stackedLinesTimeSeriesPerStackKeyAndUnit:
			invertedStackedLinesTimeSeriesPerStackKeyAndUnit,
	} = getStackedLinesTimeSeriesPerStackAndUnit({
		stackedLines: invertedStackedLines,
		timeSeries,
		invert: true,
	});

	return {
		invertedStackedLinesData: invertedStackedLinesTimeSeriesPerStackKeyAndUnit,
		stackedLinesData: regularStackedLinesTimeSeriesPerStackKeyAndUnit,
	};
};

export default useStackedLines;
