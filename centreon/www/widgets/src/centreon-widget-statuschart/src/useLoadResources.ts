import { useTheme } from '@mui/material';

import { useFetchQuery } from '@centreon/ui';

import { Resource } from '../../models';

import { buildResourcesEndpoint } from './api/endpoint';
import { StatusType } from './models';
import { FormattedResponse, formatResponse } from './utils';

interface LoadResourcesProps {
  refreshCount: number;
  refreshIntervalToUse: number | false;
  resources: Array<Resource>;
  type: 'host' | 'service';
}

interface LoadResources {
  data?: Array<FormattedResponse>;
  isLoading: boolean;
}

const useLoadResources = ({
  resources,
  refreshCount,
  refreshIntervalToUse,
  type
}: LoadResourcesProps): LoadResources => {
  const theme = useTheme();

  const { data: statuses, isLoading } = useFetchQuery<StatusType>({
    getEndpoint: () => {
      return buildResourcesEndpoint({
        resources,
        type
      });
    },
    getQueryKey: () => [
      'statusChart',
      JSON.stringify(resources),
      refreshCount,
      type
    ],
    queryOptions: {
      refetchInterval: refreshIntervalToUse,
      suspense: false
    }
  });

  return { data: formatResponse({ statuses, theme }), isLoading };
};

export default useLoadResources;
