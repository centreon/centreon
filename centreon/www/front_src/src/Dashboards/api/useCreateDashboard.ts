import {
  MutateOptions,
  UseMutationResult,
  useQueryClient
} from '@tanstack/react-query';

import {
  Method,
  ResponseError,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { dashboardsEndpoint } from './endpoints';
import { CreateDashboardDto, Dashboard, resource } from './models';

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

interface Labels {
  labelFailure: string;
  labelSuccess: string;
}

interface Props {
  labels?: Labels;
}

const useCreateDashboard = ({ labels }: Props): UseCreateDashboard => {
  const { showSuccessMessage, showErrorMessage } = useSnackbar();

  const {
    mutateAsync,
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    mutate: omittedMutate,
    ...mutationData
  } = useMutationQuery<Omit<Dashboard, 'globalRefreshInterval'>, unknown>({
    getEndpoint: () => dashboardsEndpoint,
    httpCodesBypassErrorSnackbar: [400, 500],
    method: Method.POST,
    mutationKey: [resource.dashboards, 'create'],
    ...(labels?.labelFailure
      ? { onError: () => showErrorMessage(labels?.labelFailure) }
      : {}),
    ...(labels?.labelSuccess
      ? { onSuccess: () => showSuccessMessage(labels?.labelSuccess) }
      : {})
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

    return mutateAsync(
      {
        payload: variables
      },
      {
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

export { useCreateDashboard };
