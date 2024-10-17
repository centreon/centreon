import { equals, isNotNil } from 'ramda';
import { useFetchQuery } from '../../..';
import { GetItem } from '../models';

interface UseGetItem<TItemForm> {
  initialValues?: TItemForm;
  isLoading: boolean;
}

export const useGetItem = <
  TItem extends { id: number; name: string },
  TItemForm
>({
  id,
  decoder,
  baseEndpoint,
  itemQueryKey,
  adapter
}: GetItem<TItem, TItemForm> & {
  id: number | 'add' | null;
}): UseGetItem<TItemForm> => {
  const { data, isLoading } = useFetchQuery<TItem>({
    getEndpoint: () => baseEndpoint(id),
    getQueryKey: () => [itemQueryKey, id],
    decoder,
    queryOptions: {
      enabled: isNotNil(id) && !equals('add', id),
      suspense: false
    }
  });

  return {
    initialValues: data ? adapter(data) : undefined,
    isLoading
  };
};
