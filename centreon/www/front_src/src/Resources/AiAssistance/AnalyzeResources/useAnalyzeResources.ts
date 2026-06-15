import { Method, useMutationQuery } from '@centreon/ui';

import { Resource } from '../../models';
import { aiAlertCorrelationEndpoint } from '../endpoints';
import { CorrelateAlertsResult } from '../models';

interface ResourcePayload {
  duration: string | null;
  information: string | null;
  is_acknowledged: boolean;
  is_in_downtime: boolean;
  name: string;
  parent_name: string | null;
  resource_type: string;
  status: string;
  tries: string | null;
}

interface AnalyzePayload {
  resources: Array<ResourcePayload>;
}

interface UseAnalyzeResourcesState {
  analyze: (
    resources: Array<Resource>
  ) => Promise<CorrelateAlertsResult | null>;
  isMutating: boolean;
}

const useAnalyzeResources = (): UseAnalyzeResourcesState => {
  const { mutateAsync, isMutating } = useMutationQuery<
    AnalyzePayload,
    undefined
  >({
    getEndpoint: () => aiAlertCorrelationEndpoint,
    method: Method.POST
  });

  const analyze = (
    resources: Array<Resource>
  ): Promise<CorrelateAlertsResult | null> =>
    mutateAsync({
      payload: {
        resources: resources.map((resource) => ({
          duration: resource.duration ?? null,
          information: resource.information ?? null,
          is_acknowledged: resource.is_acknowledged ?? false,
          is_in_downtime: resource.is_in_downtime ?? false,
          name: resource.name,
          parent_name: resource.parent?.name ?? null,
          resource_type: resource.type,
          status: resource.status?.name ?? 'UNKNOWN',
          tries: resource.tries ?? null
        }))
      }
    }).then((result) => {
      if ('isError' in result) {
        return null;
      }

      return result as unknown as CorrelateAlertsResult;
    });

  return { analyze, isMutating };
};

export default useAnalyzeResources;
