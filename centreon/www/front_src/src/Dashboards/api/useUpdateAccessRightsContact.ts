import {
  MutateOptions,
  UseMutationResult,
  useQueryClient
} from '@tanstack/react-query';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';

import {
  DashboardAccessRightsContact,
  resource,
  UpdateAccessRightDto
} from './models';
import { getDashboardAccessRightsContactEndpoint } from './endpoints';

type UseUpdateAccessRightsContact<
  TData extends DashboardAccessRightsContact = DashboardAccessRightsContact,
  TVariables extends UpdateAccessRightDto = UpdateAccessRightDto,
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

const useUpdateAccessRightsContact = (): UseUpdateAccessRightsContact => {
  const {
    mutateAsync,
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    mutate: omittedMutate,
    ...mutationData
  } = useMutationQuery<DashboardAccessRightsContact>({
    getEndpoint: ({ id, dashboardId }) =>
      getDashboardAccessRightsContactEndpoint(dashboardId, id),
    method: Method.PATCH,
    mutationKey: [resource.dashboardAccessRightsContacts, 'update']
  });

  const queryClient = useQueryClient();
  const invalidateQueries = ({ _meta }): Promise<void> =>
    queryClient.invalidateQueries({
      queryKey: [resource.dashboardAccessRightsContacts, _meta?.dashboardId]
    });

  const mutate = async (
    variables: UpdateAccessRightDto,
    options?: MutateOptions<
      DashboardAccessRightsContact,
      unknown,
      UpdateAccessRightDto
    >
  ): Promise<DashboardAccessRightsContact | ResponseError> => {
    const { onSettled, ...restOptions } = options || {};

    const onSettledWithInvalidateQueries = (
      data: DashboardAccessRightsContact | undefined,
      error: ResponseError | null,
      vars: UpdateAccessRightDto
    ): void => {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      invalidateQueries(vars as any);
      onSettled?.(data, error, vars, undefined);
    };

    /* eslint-disable @typescript-eslint/no-explicit-any */
    const { id, dashboardId, ...apiAllowedVariables } = variables as any;
    /* eslint-enable @typescript-eslint/no-explicit-any */

    return mutateAsync(
      {
        ...apiAllowedVariables,
        _meta: {
          dashboardId,
          id
        }
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

export { useUpdateAccessRightsContact };
