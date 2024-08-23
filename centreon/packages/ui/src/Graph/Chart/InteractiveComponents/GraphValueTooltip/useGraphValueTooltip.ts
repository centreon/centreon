import { useAtomValue } from 'jotai';
import {
  T,
  always,
  cond,
  equals,
  filter,
  isNil,
  prop,
  reverse,
  sortBy
} from 'ramda';

import { useLocaleDateTimeFormat } from '../../../../utils';
import { GraphTooltipData, Tooltip } from '../../models';
import { graphTooltipDataAtom } from '../interactionWithGraphAtoms';

interface UseGraphValueTooltipState extends Omit<GraphTooltipData, 'date'> {
  dateTime: string;
}

interface UseGraphValueTooltipProps extends Pick<Tooltip, 'sortOrder'> {
  isSingleMode: boolean;
}

export const useGraphValueTooltip = ({
  isSingleMode,
  sortOrder
}: UseGraphValueTooltipProps): UseGraphValueTooltipState | null => {
  const { toDate, toTime } = useLocaleDateTimeFormat();
  const graphTooltipData = useAtomValue(graphTooltipDataAtom);

  if (isNil(graphTooltipData) || isNil(graphTooltipData.metrics)) {
    return null;
  }

  const formattedDateTime = `${toDate(graphTooltipData.date)} / ${toTime(graphTooltipData.date)}`;

  const filteredMetrics = isSingleMode
    ? filter(
        ({ id }) => equals(id, graphTooltipData.highlightedMetricId),
        graphTooltipData.metrics
      )
    : graphTooltipData.metrics;

  const sortedMetrics = cond([
    [equals('name'), always(sortBy(prop('name'), filteredMetrics))],
    [equals('ascending'), always(sortBy(prop('value'), filteredMetrics))],
    [
      equals('descending'),
      always(reverse(sortBy(prop('value'), filteredMetrics)))
    ],
    [T, always(filteredMetrics)]
  ])(sortOrder);

  return {
    dateTime: formattedDateTime,
    highlightedMetricId: graphTooltipData.highlightedMetricId,
    metrics: sortedMetrics
  };
};
