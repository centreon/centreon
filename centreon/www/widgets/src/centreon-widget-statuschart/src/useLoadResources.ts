import { useTheme } from '@mui/material';

import { useFetchQuery } from '@centreon/ui';

import { Resource } from '../../models';

import { buildResourcesEndpoint } from './api/endpoint';
import { StatusType } from './models';
import { FormattedResponse, formatResponse } from './utils';

interface LoadResourcesProps {
  refreshCount: number;
  refreshIntervalToUse: number | false;
  resourceType: 'host' | 'service';
  resources: Array<Resource>;
}

interface LoadResources {
  data?: Array<FormattedResponse>;
  isLoading: boolean;
}

const useLoadResources = ({
  resources,
  refreshCount,
  refreshIntervalToUse,
  resourceType
}: LoadResourcesProps): LoadResources => {
  const theme = useTheme();

  const { data: statuses, isLoading } = useFetchQuery<StatusType>({
    getEndpoint: () => {
      return buildResourcesEndpoint({
        resources,
        type: resourceType
      });
    },
    getQueryKey: () => [
      'statusChart',
      JSON.stringify(resources),
      refreshCount,
      resourceType
    ],
    queryOptions: {
      refetchInterval: refreshIntervalToUse,
      suspense: false
    }
  });

  return { data: formatResponse({ statuses, theme }), isLoading };
};

export default useLoadResources;
