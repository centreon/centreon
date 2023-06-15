import dayjs from 'dayjs';
import { gt, gte, isEmpty, isNil, prop, propEq, reject, sortBy } from 'ramda';

import { LinesData } from '../BasicComponents/Lines/models';
import { dateFormat, timeFormat } from '../common';
import { GraphData, GraphInterval } from '../models';
import { getLineData, getTimeSeries } from '../timeSeries';

export const adjustGraphData = (graphData: GraphData): LinesData => {
  const lines = getLineData(graphData);
  const sortedLines = sortBy(prop('name'), lines);
  const displayedLines = reject(propEq('display', false), sortedLines);

  const timeSeries = getTimeSeries(graphData);

  return { lines: displayedLines, timeSeries };
};

export const getXAxisTickFormat = (graphInterval: GraphInterval): string => {
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

export const truncate = (content?: string): string => {
  const maxLength = 180;

  if (isNil(content)) {
    return '';
  }

  if (gt(content.length, maxLength)) {
    return `${content.substring(0, maxLength)}...`;
  }

  return content;
};

export const displayArea = (data: unknown): boolean =>
  !isEmpty(data) && !isNil(data);
