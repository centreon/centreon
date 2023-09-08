import { always, cond, equals } from 'ramda';

interface Props {
  globalRefreshInterval?: number;
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
}

export const useRefreshInterval = ({
  refreshInterval,
  refreshIntervalCustom,
  globalRefreshInterval
}: Props): number | false => {
  const refreshIntervalToUse = cond([
    [equals('default'), always(globalRefreshInterval)],
    [equals('custom'), always(refreshIntervalCustom)],
    [equals('manual'), always(0)]
  ])(refreshInterval);

  return refreshIntervalToUse ? refreshIntervalToUse * 1000 : false;
};
