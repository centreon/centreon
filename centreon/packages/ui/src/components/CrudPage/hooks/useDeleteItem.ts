import {
  Method,
  ResponseError,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';
import { useQueryClient } from '@tanstack/react-query';
import { ReactElement } from 'react';
import { ItemToDelete } from '../models';
import { isAFunction } from '../utils';

interface UseDeleteItem {
  isMutating: boolean;
  deleteItem: (item: ItemToDelete) => Promise<object | ResponseError>;
}

interface UseDeleteItemProps {
  deleteEndpoint: (item: ItemToDelete) => string;
  listingQueryKey: string;
  successMessage:
    | ((item: ItemToDelete) => string | ReactElement)
    | string
    | ReactElement;
}

export const useDeleteItem = ({
  deleteEndpoint,
  listingQueryKey,
  successMessage
}: UseDeleteItemProps): UseDeleteItem => {
  const queryClient = useQueryClient();

  const { showSuccessMessage } = useSnackbar();

  const { mutateAsync, isMutating } = useMutationQuery<object, ItemToDelete>({
    getEndpoint: (_meta) => deleteEndpoint(_meta),
    method: Method.DELETE,
    onSuccess: (_data, { _meta }) => {
      queryClient.invalidateQueries({ queryKey: [listingQueryKey] });
      showSuccessMessage(
        isAFunction(successMessage) ? successMessage(_meta) : successMessage
      );
    }
  });

  const deleteItem = (item: ItemToDelete): Promise<object | ResponseError> =>
    mutateAsync({ _meta: item });

  return {
    isMutating,
    deleteItem
  };
};
