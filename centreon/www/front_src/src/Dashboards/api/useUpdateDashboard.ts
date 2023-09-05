import {
  MutateOptions,
  UseMutationResult,
  useQueryClient
} from '@tanstack/react-query';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';

import { Dashboard, resource, UpdateDashboardDto } from './models';
import { getDashboardEndpoint } from './endpoints';

type UseUpdateDashboard<
  TData extends Dashboard = Dashboard,
  TVariables extends UpdateDashboardDto = UpdateDashboardDto,
  TError = ResponseError
> = {
  mutate: (
    variables: TVariables,
    options?: MutateOptions<TData, TError, TVariables>
  ) => Promise<TData | TError>;
} & Omit<
  UseMutationResult<TData | TError, TError, TVariables>,
  'mutate' | 'mutateAsync'
>;

const useUpdateDashboard = (): UseUpdateDashboard => {
  const {
    mutateAsync,
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    mutate: omittedMutate,
    ...mutationData
  } = useMutationQuery<Dashboard>({
    getEndpoint: ({ id }) => getDashboardEndpoint(id),
    method: Method.PATCH
  });

  const queryClient = useQueryClient();
  const invalidateQueries = (): Promise<void> =>
    queryClient.invalidateQueries({
      queryKey: [resource.dashboards]
    });

  const mutate = async (
    variables: UpdateDashboardDto,
    options?: MutateOptions<Dashboard, unknown, UpdateDashboardDto>
  ): Promise<Dashboard | ResponseError> => {
    const { onSettled, ...restOptions } = options || {};

    const onSettledWithInvalidateQueries = (
      data: Dashboard | undefined,
      error: ResponseError | null,
      vars: UpdateDashboardDto
    ): void => {
      invalidateQueries();
      onSettled?.(data, error, vars, undefined);
    };

    /* eslint-disable @typescript-eslint/no-unused-vars */
    /* eslint-disable @typescript-eslint/no-explicit-any */
    const {
      id,
      createdAt,
      updatedAt,
      createdBy,
      updatedBy,
      ownRole,
      ...apiAllowedVariables
    } = variables as any;
    /* eslint-enable @typescript-eslint/no-unused-vars */
    /* eslint-enable @typescript-eslint/no-explicit-any */

    return mutateAsync(
      {
        ...apiAllowedVariables,
        _meta: {
          id
        }
      },
      {
        mutationKey: [resource.dashboards, 'update', id],
        onSettled: onSettledWithInvalidateQueries,
        ...restOptions
      }
    );
  };

  return {
    mutate,
    ...mutationData
  };
};

export { useUpdateDashboard };
