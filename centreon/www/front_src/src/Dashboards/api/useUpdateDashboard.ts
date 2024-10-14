import { UseMutationResult, useQueryClient } from '@tanstack/react-query';
import { pick } from 'ramda';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';

import { getDashboardEndpoint } from './endpoints';
import { Dashboard, resource } from './models';

type UseUpdateDashboard = {
  mutate: (variables: Dashboard) => Promise<Dashboard | ResponseError>;
} & Pick<
  UseMutationResult<Dashboard | ResponseError, ResponseError, Dashboard>,
  'reset' | 'status'
>;

const useUpdateDashboard = (): UseUpdateDashboard => {
  const queryClient = useQueryClient();

  const invalidateQueries = (): void => {
    queryClient.invalidateQueries({
      queryKey: [resource.dashboards]
    });
    queryClient.invalidateQueries({
      queryKey: [resource.dashboard]
    });
  };

  const { mutateAsync, ...mutationData } = useMutationQuery<
    Dashboard,
    Dashboard
  >({
    getEndpoint: ({ id }) => getDashboardEndpoint(id),
    method: Method.POST,
    onSuccess: invalidateQueries
  });

  const mutate = (variables: Dashboard): Promise<Dashboard | ResponseError> => {
    const formData = new FormData();

    pick(['name', 'description', 'refresh'], variables);

    formData.append('name', variables.name);
    formData.append('description', variables.description);

    if (variables?.refresh) {
      formData.append('refresh[type]', variables.refresh.type);
      if (variables.refresh.interval){
        formData.append(
          'refresh[interval]',
          JSON.stringify(variables.refresh.interval)
        );
      }
    }

    return mutateAsync({
      _meta: {
        id: variables.id
      },
      payload: formData
    });
  };

  return {
    mutate,
    reset: mutationData.reset,
    status: mutationData.status
  };
};

export { useUpdateDashboard };
