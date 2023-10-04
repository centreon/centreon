import { UseMutationResult, useQueryClient } from '@tanstack/react-query';
import { omit } from 'ramda';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';

import { Dashboard, resource } from './models';
import { getDashboardEndpoint } from './endpoints';

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
    method: Method.PATCH,
    onSuccess: invalidateQueries
  });

  const mutate = (variables: Dashboard): Promise<Dashboard | ResponseError> =>
    mutateAsync({
      _meta: {
        id: variables.id
      },
      ...omit(
        ['id', 'createdAt', 'updatedAt', 'createdBy', 'updatedBy', 'ownRole'],
        variables
      )
    });

  return {
    mutate,
    reset: mutationData.reset,
    status: mutationData.status
  };
};

export { useUpdateDashboard };
