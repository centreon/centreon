import type { QueryParameter } from '@centreon/ui';

import dayjs from 'dayjs';
import { useAtomValue } from 'jotai';
import { isEmpty, isNil } from 'ramda';
import { useMemo } from 'react';

import { filtersAtom } from './atoms';
import { type Filters, Period } from './models';

export const presetDurations: Partial<
  Record<Period, [number, dayjs.ManipulateType]>
> = {
  [Period.LastHour]: [1, 'hour'],
  [Period.Last24Hours]: [24, 'hour'],
  [Period.Last7Days]: [7, 'day'],
  [Period.Last30Days]: [30, 'day']
};

// Presets are resolved when the filters change, so the bounds stay the same
// while the next pages are loaded
const getDateRange = ({
  period,
  from,
  to
}: Filters): { from: Date | null; to: Date | null } => {
  if (period === Period.Custom) {
    return { from, to };
  }

  const duration = presetDurations[period];

  return {
    from: duration
      ? dayjs()
          .subtract(...duration)
          .toDate()
      : null,
    to: null
  };
};

interface Props {
  target?: { id: number; type: string };
}

const toArrayParameters = (
  name: string,
  values: Array<string | number>
): Array<QueryParameter> => values.map((value) => ({ name, value }));

export const useActivityLogsQueryParameters = ({
  target
}: Props = {}): Array<QueryParameter> => {
  const filters = useAtomValue(filtersAtom);

  return useMemo(() => {
    const { from, to } = getDateRange(filters);

    if (target) {
      return [
        { name: 'target_type[]', value: target.type },
        { name: 'target_id', value: target.id }
      ];
    }

    return [
      ...(isEmpty(filters.search)
        ? []
        : [{ name: 'object_name', value: filters.search }]),
      ...toArrayParameters(
        'actor_id[]',
        filters.actors.map(({ id }) => id)
      ),
      ...toArrayParameters(
        'token_name[]',
        filters.tokens.map(({ id }) => id)
      ),
      ...toArrayParameters(
        'target_type[]',
        filters.targetTypes.map(({ id }) => id)
      ),
      ...toArrayParameters(
        'action[]',
        filters.actions.map(({ id }) => id)
      ),
      ...(isNil(from)
        ? []
        : [{ name: 'date[from]', value: from.toISOString() }]),
      ...(isNil(to) ? [] : [{ name: 'date[to]', value: to.toISOString() }])
    ];
  }, [filters, target]);
};
