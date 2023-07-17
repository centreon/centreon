import {
  MutateOptions,
  UseMutationResult,
  useQueryClient
} from '@tanstack/react-query';

import { Method, ResponseError, useMutationQuery } from '@centreon/ui';

import {
  DashboardAccessRightsContactGroup,
  resource,
  UpdateAccessRightDto
} from './models';
import { getDashboardAccessRightsContactGroupEndpoint } from './endpoints';

type UseUpdateAccessRightsContactGroup<
  TData extends DashboardAccessRightsContactGroup = DashboardAccessRightsContactGroup,
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

const useUpdateAccessRightsContactGroup =
  (): UseUpdateAccessRightsContactGroup => {
    const {
      mutateAsync,
      // eslint-disable-next-line @typescript-eslint/no-unused-vars
      mutate: omittedMutate,
      ...mutationData
    } = useMutationQuery<DashboardAccessRightsContactGroup>({
      getEndpoint: ({ id, dashboardId }) =>
        getDashboardAccessRightsContactGroupEndpoint(dashboardId, id),
      method: Method.PATCH,
      mutationKey: [resource.dashboardAccessRightsContactGroups, 'update']
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
      variables: UpdateAccessRightDto,
      options?: MutateOptions<
        DashboardAccessRightsContactGroup,
        unknown,
        UpdateAccessRightDto
      >
    ): Promise<DashboardAccessRightsContactGroup | ResponseError> => {
      const { onSettled, ...restOptions } = options || {};

      const onSettledWithInvalidateQueries = (
        data: DashboardAccessRightsContactGroup | undefined,
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

export { useUpdateAccessRightsContactGroup };
