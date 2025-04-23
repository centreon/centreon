import { equals, includes } from 'ramda';

import { SeverityCode, useFetchQuery } from '@centreon/ui';

import { metricsDecoder } from '../api/decoders';
import { ResourceData } from '../models';
import { getMetricsEndpoint, getStatusFromThresholds } from '../utils';

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
  const isMetaService = equals(data?.type, 'meta-service');

  const isQueryEnabled =
    !!data.metricsEndpoint &&
    (equals(data.status, SeverityCode.High) ||
      equals(data.status, SeverityCode.Medium));

  const getEndpoint = (): string => {
    if (data.metricsEndpoint) {
      return data.metricsEndpoint;
    }

    return getMetricsEndpoint({
      id: data?.resourceId,
      parentId: data?.parentId,
      resouceType: data?.type
    });
  };

  const { data: metricsData, isLoading } = useFetchQuery({
    decoder: isMetaService ? undefined : metricsDecoder,
    getEndpoint,
    getQueryKey: () => ['statusgrid', 'metrics', data.metricsEndpoint, data.id],
    httpCodesBypassErrorSnackbar: [404],
    queryOptions: {
      enabled: isQueryEnabled,
      suspense: false
    }
  });

  const metrics = isMetaService ? metricsData?.result : metricsData;

  const problematicMetrics = (metrics || [])
    ?.map(
      ({
        criticalHighThreshold,
        criticalLowThreshold,
        currentValue,
        value,
        name,
        warningHighThreshold,
        warningLowThreshold,
        resource,
        unit
      }) => {
        const status = isMetaService
          ? undefined
          : getStatusFromThresholds({
              criticalThresholds: [criticalLowThreshold, criticalHighThreshold],
              data: currentValue,
              warningThresholds: [warningLowThreshold, warningHighThreshold]
            });

        return {
          name: isMetaService
            ? `${resource?.parent.name}_${resource?.name}: (${name})`
            : name,
          status,
          value: `${isMetaService ? value : currentValue} (${unit})`
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
