import { useAtomValue } from 'jotai';
import { isNil } from 'ramda';

import { graphTooltipDataAtom } from '../interactionWithGraphAtoms';
import { useLocaleDateTimeFormat } from '../../../../utils';
import { GraphTooltipData } from '../../models';

interface UseGraphValueTooltipState extends Omit<GraphTooltipData, 'date'> {
  dateTime: string;
}

export const useGraphValueTooltip = (): UseGraphValueTooltipState | null => {
  const { toDate, toTime } = useLocaleDateTimeFormat();
  const graphTooltipData = useAtomValue(graphTooltipDataAtom);

  if (isNil(graphTooltipData)) {
    return null;
  }

  const formattedDateTime = `${toDate(graphTooltipData.date)} / ${toTime(graphTooltipData.date)}`;

  return {
    dateTime: formattedDateTime,
    highlightedMetricId: graphTooltipData.highlightedMetricId,
    metrics: graphTooltipData.metrics
  };
};
