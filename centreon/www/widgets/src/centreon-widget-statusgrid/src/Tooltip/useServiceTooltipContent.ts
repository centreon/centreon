import { equals, includes } from 'ramda';

import { SeverityCode, useFetchQuery } from '@centreon/ui';

import { ResourceData } from '../models';
import { metricsDecoder } from '../api/decoders';
import { getStatusFromThresholds } from '../utils';

interface UseServiceTooltipContentState {
  isLoading: boolean;
  problematicMetrics: Array<{
    name: string;
    status: SeverityCode;
    value: number | null;
  }>;
}

const isProblematicMetric = ({ status }): boolean =>
  includes(status, [SeverityCode.High, SeverityCode.Medium]);

const useServiceTooltipContent = (
  data: ResourceData
): UseServiceTooltipContentState => {
  const isQueryEnabled =
    !!data.metricsEndpoint &&
    (equals(data.status, SeverityCode.High) ||
      equals(data.status, SeverityCode.Medium));

  const { data: metricsData, isLoading } = useFetchQuery({
    decoder: metricsDecoder,
    getEndpoint: () => data.metricsEndpoint as string,
    getQueryKey: () => ['statusgrid', 'metrics', data.metricsEndpoint],
    httpCodesBypassErrorSnackbar: [404],
    queryOptions: {
      enabled: isQueryEnabled,
      suspense: false
    }
  });

  const problematicMetrics = (metricsData || [])
    .map(
      ({
        criticalHighThreshold,
        criticalLowThreshold,
        currentValue,
        name,
        warningHighThreshold,
        warningLowThreshold
      }) => {
        const status = getStatusFromThresholds({
          criticalThresholds: [criticalLowThreshold, criticalHighThreshold],
          data: currentValue,
          warningThresholds: [warningLowThreshold, warningHighThreshold]
        });

        return {
          name,
          status,
          value: currentValue
        };
      }
    )
    .filter(isProblematicMetric);

  return {
    isLoading,
    problematicMetrics
  };
};

export default useServiceTooltipContent;
