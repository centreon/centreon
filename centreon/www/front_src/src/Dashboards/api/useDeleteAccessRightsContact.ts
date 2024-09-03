import {
  MutateOptions,
  UseMutationResult,
  useQueryClient
} from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import {
  Method,
  ResponseError,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

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
      data: undefined,
      error: ResponseError | null,
      vars: DeleteAccessRightDto
    ): void => {
      invalidateQueries(vars);
      onSettled?.(data, error, vars, undefined);
    };

    const { id, dashboardId } = variables;

    return mutateAsync(
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
    ).then(() => {
      queryClient.invalidateQueries({
        queryKey: [resource.dashboards]
      });

      showSuccessMessage(t(labelUserDeleted));
    });
  };

  return {
    mutate,
    ...mutationData
  };
};

export { useDeleteAccessRightsContact };
