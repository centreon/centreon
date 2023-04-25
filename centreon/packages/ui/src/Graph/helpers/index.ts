import { prop, propEq, reject, sortBy } from 'ramda';

import { getLineData, getTimeSeries } from '../timeSeries';
import { LinesData } from '../Lines/models';
import { GraphData } from '../models';

export const adjustGraphData = (graphData: GraphData): LinesData => {
  const lines = getLineData(graphData);
  const sortedLines = sortBy(prop('name'), lines);
  const displayedLines = reject(propEq('display', false), sortedLines);

  const timeSeries = getTimeSeries(graphData);

  return { lines: displayedLines, timeSeries };
};
