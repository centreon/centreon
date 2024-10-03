import {
  Method,
  ResponseError,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';
import { useQueryClient } from '@tanstack/react-query';
import { ReactElement } from 'react';
import { isAFunction } from '../utils';

interface UseDeleteItem<TData> {
  isMutating: boolean;
  deleteItem: (item: TData) => Promise<object | ResponseError>;
}

interface UseDeleteItemProps<TData> {
  deleteEndpoint: (item: TData) => string;
  listingQueryKey: string;
  successMessage:
    | ((item: TData) => string | ReactElement)
    | string
    | ReactElement;
}

export const useDeleteItem = <TData extends { id: number; name: string }>({
  deleteEndpoint,
  listingQueryKey,
  successMessage
}: UseDeleteItemProps<TData>): UseDeleteItem<TData> => {
  const queryClient = useQueryClient();

  const { showSuccessMessage } = useSnackbar();

  const { mutateAsync, isMutating } = useMutationQuery<object, TData>({
    getEndpoint: (_meta) => deleteEndpoint(_meta),
    method: Method.DELETE,
    onSuccess: (_data, { _meta }) => {
      console.log(_meta);

      queryClient.invalidateQueries({ queryKey: [listingQueryKey] });
      showSuccessMessage(
        isAFunction(successMessage) ? successMessage(_meta) : successMessage
      );
    }
  });

  const deleteItem = (item: TData): Promise<object | ResponseError> =>
    mutateAsync({ _meta: item });

  return {
    isMutating,
    deleteItem
  };
};
