import {
  MutateOptions,
  UseMutationResult,
  useQueryClient
} from '@tanstack/react-query';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';

import { getDashboardEndpoint } from './endpoints';
import { Dashboard, DeleteDashboardDto, resource } from './models';

type UseDeleteDashboard<
  TData extends null = null,
  TVariables extends DeleteDashboardDto = DeleteDashboardDto,
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

const useDeleteDashboard = (): UseDeleteDashboard => {
  const {
    mutateAsync,
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    mutate: omittedMutate,
    ...mutationData
  } = useMutationQuery<Dashboard, { id }>({
    getEndpoint: ({ id }) => getDashboardEndpoint(id),
    method: Method.DELETE
  });

  const queryClient = useQueryClient();
  const invalidateQueries = (): Promise<void> =>
    queryClient.invalidateQueries({
      queryKey: [resource.dashboards]
    });

  const mutate = async (
    variables: DeleteDashboardDto,
    options?: MutateOptions<Dashboard, unknown, DeleteDashboardDto>
  ): Promise<Dashboard | ResponseError> => {
    const { onSettled, ...restOptions } = options || {};

    const onSettledWithInvalidateQueries = (
      data: undefined,
      error: ResponseError | null,
      vars: DeleteDashboardDto
    ): void => {
      invalidateQueries();
      onSettled?.(data, error, vars, undefined);
    };

    const { id } = variables;

    return mutateAsync(
      { _meta: { id } },
      {
        mutationKey: [resource.dashboards, 'delete', id],
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

export { useDeleteDashboard };
