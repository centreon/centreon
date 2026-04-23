import {
  Method,
  ResponseError,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import {
  MutateOptions,
  UseMutationResult,
  useQueryClient
} from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { labelUserDeleted } from '../translatedLabels';
import { getDashboardAccessRightsContactEndpoint } from './endpoints';
import { DeleteAccessRightDto, resource } from './models';

type UseDeleteAccessRightsContact<
  TData extends null = null,
  TVariables extends DeleteAccessRightDto = DeleteAccessRightDto,
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

const useDeleteAccessRightsContact = (): UseDeleteAccessRightsContact => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const {
    mutateAsync,
    mutate: omittedMutate,
    ...mutationData
  } = useMutationQuery<object, { dashboardId; id }>({
    getEndpoint: ({ id, dashboardId }) =>
      getDashboardAccessRightsContactEndpoint(dashboardId, id),
    method: Method.DELETE,
    mutationKey: [resource.dashboardAccessRightsContacts, 'delete']
  });

  const queryClient = useQueryClient();
  const invalidateQueries = ({ _meta }): Promise<void> =>
    queryClient.invalidateQueries({
      queryKey: [resource.dashboardAccessRightsContacts, _meta?.dashboardId]
    });

  const mutate = async (
    variables: DeleteAccessRightDto,
    options?: MutateOptions<object, unknown, DeleteAccessRightDto>
  ): Promise<object | ResponseError> => {
    const { onSettled, ...restOptions } = options || {};

    const onSettledWithInvalidateQueries = (
      data:
        | {
            _meta?:
              | { dashboardId: string | number; id: string | number }
              | undefined;
            payload: object;
          }
        | undefined,
      error: Error | null,
      vars: {
        _meta?:
          | { dashboardId: string | number; id: string | number }
          | undefined;
        payload?: object;
      },
      context: unknown
    ): void => {
      if (vars._meta) {
        invalidateQueries({ _meta: vars._meta });
      }
      onSettled?.(data, error, variables, context);
    };

    const { id, dashboardId } = variables;

    try {
      const result = await mutateAsync(
        {
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

      await queryClient.invalidateQueries({
        queryKey: [resource.dashboards]
      });

      showSuccessMessage(t(labelUserDeleted));
      return result;
    } catch (error) {
      return error as ResponseError;
    }
  };

  return {
    mutate,
    ...mutationData
  };
};

export { useDeleteAccessRightsContact };
