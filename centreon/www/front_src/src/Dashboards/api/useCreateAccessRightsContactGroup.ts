import {
  MutateOptions,
  UseMutationResult,
  useQueryClient
} from '@tanstack/react-query';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';

import {
  CreateAccessRightDto,
  DashboardAccessRightsContactGroup,
  resource
} from './models';
import { getDashboardAccessRightsContactGroupsEndpoint } from './endpoints';
import { dashboardAccessRightsContactGroupDecoder } from './decoders';

type UseCreateAccessRightsContactGroup<
  TData extends DashboardAccessRightsContactGroup = DashboardAccessRightsContactGroup,
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

const useCreateAccessRightsContactGroup =
  (): UseCreateAccessRightsContactGroup => {
    const {
      mutateAsync,
      // eslint-disable-next-line @typescript-eslint/no-unused-vars
      mutate: omittedMutate,
      ...mutationData
    } = useMutationQuery<DashboardAccessRightsContactGroup>({
      decoder: dashboardAccessRightsContactGroupDecoder,
      getEndpoint: ({ dashboardId }) =>
        getDashboardAccessRightsContactGroupsEndpoint(dashboardId),
      method: Method.POST,
      mutationKey: [resource.dashboardAccessRightsContactGroups, 'create']
    });

    const queryClient = useQueryClient();
    const invalidateQueries = ({ _meta }): Promise<void> =>
      queryClient.invalidateQueries({
        queryKey: [
          resource.dashboardAccessRightsContactGroups,
          _meta?.dashboardId
        ]
      });

    const mutate = async (
      variables: CreateAccessRightDto,
      options?: MutateOptions<
        DashboardAccessRightsContactGroup,
        unknown,
        CreateAccessRightDto
      >
    ): Promise<DashboardAccessRightsContactGroup | ResponseError> => {
      const { onSettled, ...restOptions } = options || {};

      const onSettledWithInvalidateQueries = (
        data: DashboardAccessRightsContactGroup | undefined,
        error: ResponseError | null,
        vars: CreateAccessRightDto
      ): void => {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        invalidateQueries(vars as any);
        onSettled?.(data, error, vars, undefined);
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
      );
    };

    return {
      mutate,
      ...mutationData
    };
  };

export { useCreateAccessRightsContactGroup };
