import { useFetchQuery } from '@centreon/ui';
import { useListingQueryKey } from './useListingQueryKey';

interface UseGetAgentConfigurationsState {
  data: Array<unknown>;
  isLoading: boolean;
  isEmpty: boolean;
}

export const useGetAgentConfigurations = (): UseGetAgentConfigurationsState => {
  const queryKey = useListingQueryKey();

  const { data } = useFetchQuery({
    getQueryKey: () => queryKey,
    getEndpoint: () => '/test'
  });

  return {
    data: [],
    isEmpty: true,
    isLoading: false
  };
};
