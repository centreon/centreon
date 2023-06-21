import {
  MutateOptions,
  UseMutationResult,
  useQueryClient
} from '@tanstack/react-query';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';

import { CreateDashboardDto, Dashboard, resource } from './models';
import { dashboardsEndpoint } from './endpoints';
import { dashboardDecoder } from './decoders';

type UseCreateDashboard<
  TData extends Dashboard = Dashboard,
  TVariables extends CreateDashboardDto = CreateDashboardDto,
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

const useCreateDashboard = (): UseCreateDashboard => {
  const {
    mutateAsync,
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    mutate: omittedMutate,
    ...mutationData
  } = useMutationQuery<Dashboard>({
    decoder: dashboardDecoder,
    getEndpoint: () => dashboardsEndpoint,
    method: Method.POST,
    mutationKey: [resource.dashboards, 'create']
  });

  const queryClient = useQueryClient();
  const invalidateQueries = (): Promise<void> =>
    queryClient.invalidateQueries({
      queryKey: [resource.dashboards]
    });

  const mutate = async (
    variables: CreateDashboardDto,
    options?: MutateOptions<Dashboard, unknown, CreateDashboardDto>
  ): Promise<Dashboard | ResponseError> => {
    const { onSettled, ...restOptions } = options || {};

    const onSettledWithInvalidateQueries = (
      data: Dashboard | undefined,
      error: ResponseError | null,
      vars: CreateDashboardDto
    ): void => {
      invalidateQueries();
      onSettled?.(data, error, vars, undefined);
    };

    return mutateAsync(variables, {
      onSettled: onSettledWithInvalidateQueries,
      ...restOptions
    });
  };

  return {
    mutate,
    ...mutationData
  };
};

export { useCreateDashboard };
