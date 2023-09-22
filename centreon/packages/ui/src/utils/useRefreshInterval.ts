import { useAtomValue } from 'jotai';
import { always, cond, equals } from 'ramda';

import { refreshIntervalAtom } from '@centreon/ui-context';

interface Props {
  globalRefreshInterval: {
    interval: number | null;
    type: 'global' | 'manual';
  };
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
}

export const useRefreshInterval = ({
  refreshInterval,
  refreshIntervalCustom,
  globalRefreshInterval
}: Props): number | false => {
  const platformInterval = useAtomValue(refreshIntervalAtom);

  const refreshIntervalToUse = cond([
    [
      equals('default'),
      always(
        equals(globalRefreshInterval.type, 'manual')
          ? false
          : globalRefreshInterval.interval || platformInterval
      )
    ],
    [equals('custom'), always(refreshIntervalCustom)],
    [equals('manual'), always(false)]
  ])(refreshInterval);

  return refreshIntervalToUse ? (refreshIntervalToUse as number) * 1000 : false;
};
