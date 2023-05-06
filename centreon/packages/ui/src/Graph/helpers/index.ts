import { gte, isNil, prop, propEq, reject, sortBy } from 'ramda';
import dayjs from 'dayjs';

import { getLineData, getTimeSeries } from '../timeSeries';
import { LinesData } from '../Lines/models';
import { GraphData, GraphParameters } from '../models';
import { dateFormat, timeFormat } from '../common';

export const adjustGraphData = (graphData: GraphData): LinesData => {
  const lines = getLineData(graphData);
  const sortedLines = sortBy(prop('name'), lines);
  const displayedLines = reject(propEq('display', false), sortedLines);

  const timeSeries = getTimeSeries(graphData);

  return { lines: displayedLines, timeSeries };
};

export const getXAxisTickFormat = (graphInterval: GraphParameters): string => {
  if (
    isNil(graphInterval) ||
    isNil(graphInterval?.start) ||
    isNil(graphInterval?.end)
  ) {
    return timeFormat;
  }
  const { end, start } = graphInterval;
  const numberDays = dayjs.duration(dayjs(end).diff(dayjs(start))).asDays();

  return gte(numberDays, 2) ? dateFormat : timeFormat;
};
