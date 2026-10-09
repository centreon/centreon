import { useAtomValue } from 'jotai';
import { always, cond, equals, isNil, prop, reverse, sortBy, T } from 'ramda';

import { useLocaleDateTimeFormat } from '../../../../utils';
import { isMetricDisplayedInTooltip } from '../../../common/utils';
import type { GraphTooltipData, Tooltip } from '../../models';
import { graphTooltipDataAtom } from '../interactionWithGraphAtoms';

interface UseGraphValueTooltipState extends Omit<GraphTooltipData, 'date'> {
  dateTime: string;
}

interface UseGraphValueTooltipProps
  extends Pick<Tooltip, 'sortOrder'>,
    Partial<Pick<Tooltip, 'mode'>> {}

export const useGraphValueTooltip = ({
  mode,
  sortOrder
}: UseGraphValueTooltipProps): UseGraphValueTooltipState | null => {
  const { format } = useLocaleDateTimeFormat();
  const graphTooltipData = useAtomValue(graphTooltipDataAtom);

  if (isNil(graphTooltipData) || isNil(graphTooltipData.metrics)) {
    return null;
  }

  const filteredMetrics = graphTooltipData.metrics.filter(({ id, value }) =>
    isMetricDisplayedInTooltip({
      isHighlighted: equals(id, graphTooltipData.highlightedMetricId),
      mode,
      value
    })
  );

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
    dateTime: format({ date: graphTooltipData.date, formatString: 'L LTS' }),
    highlightedMetricId: graphTooltipData.highlightedMetricId,
    metrics: sortedMetrics
  };
};
