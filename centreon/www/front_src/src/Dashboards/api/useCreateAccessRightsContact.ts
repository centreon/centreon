import {
  MutateOptions,
  UseMutationResult,
  useQueryClient
} from '@tanstack/react-query';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';

import {
  CreateAccessRightDto,
  DashboardAccessRightsContact,
  resource
} from './models';
import { getDashboardAccessRightsContactsEndpoint } from './endpoints';
import { dashboardAccessRightsContactDecoder } from './decoders';

type UseCreateAccessRightsContact<
  TData extends DashboardAccessRightsContact = DashboardAccessRightsContact,
  TVariables extends CreateAccessRightDto = CreateAccessRightDto,
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

const useCreateAccessRightsContact = (): UseCreateAccessRightsContact => {
  const {
    mutateAsync,
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    mutate: omittedMutate,
    ...mutationData
  } = useMutationQuery<DashboardAccessRightsContact, { dashboardId }>({
    decoder: dashboardAccessRightsContactDecoder,
    getEndpoint: ({ dashboardId }) =>
      getDashboardAccessRightsContactsEndpoint(dashboardId),
    method: Method.POST,
    mutationKey: [resource.dashboardAccessRightsContacts, 'create']
  });

  const queryClient = useQueryClient();
  const invalidateQueries = ({ _meta }): Promise<void> =>
    queryClient.invalidateQueries({
      queryKey: [resource.dashboardAccessRightsContacts, _meta?.dashboardId]
    });

  const mutate = async (
    variables: CreateAccessRightDto,
    options?: MutateOptions<
      DashboardAccessRightsContact,
      unknown,
      CreateAccessRightDto
    >
  ): Promise<DashboardAccessRightsContact | ResponseError> => {
    const { onSettled, ...restOptions } = options || {};

    const onSettledWithInvalidateQueries = (
      data: DashboardAccessRightsContact | ResponseError | undefined,
      error: Error | null,
      vars: unknown
    ): void => {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      invalidateQueries(vars as any);
      onSettled?.(
        data as DashboardAccessRightsContact | undefined,
        error,
        vars as CreateAccessRightDto,
        undefined
      );
    };

    /* eslint-disable @typescript-eslint/no-explicit-any */
    const { dashboardId, ...apiAllowedVariables } = variables as any;
    /* eslint-enable @typescript-eslint/no-explicit-any */

    return mutateAsync(
      {
        ...apiAllowedVariables,
        _meta: {
          dashboardId
        }
      },
      {
        onSettled: onSettledWithInvalidateQueries,
        ...restOptions
      }
    ).then(() => {
      queryClient.invalidateQueries({
        queryKey: [resource.dashboards]
      });
    });
  };

  return {
    mutate,
    ...mutationData
  };
};

export { useCreateAccessRightsContact };
