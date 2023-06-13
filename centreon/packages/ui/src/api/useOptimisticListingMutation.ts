import { useQueryClient } from '@tanstack/react-query';

import { ListingModel } from '..';

interface ApplyListingMutationProps<T> {
  getNewData: (listing: ListingModel<T>) => ListingModel<T>;
  page: number;
  queryKey: Array<string>;
}

interface RollBackListingMutation {
  context;
  page: number;
  queryKey: Array<string>;
}

interface UseOptimisticListingMutationState<T> {
  applyListingMutation: (props: ApplyListingMutationProps<T>) => Promise<{
    previousListing: ListingModel<T>;
  }>;
  rollBackListingMutation: (props: RollBackListingMutation) => void;
}

const useOptimisticListingMutation = <
  T extends { id: string | number }
>(): UseOptimisticListingMutationState<T> => {
  const queryClient = useQueryClient();

  const applyListingMutation = async ({
    page,
    queryKey,
    getNewData
  }: ApplyListingMutationProps<T>): Promise<{
    previousListing: ListingModel<T>;
  }> => {
    await queryClient.cancelQueries({ queryKey });
    const previousListing = queryClient.getQueriesData(
      queryKey
    )[0][1] as ListingModel<T>;

    const newData = getNewData(previousListing);

    queryClient.setQueryData([...queryKey, page], newData);

    return { previousListing };
  };

  const rollBackListingMutation = ({
    context,
    page,
    queryKey
  }: RollBackListingMutation): void => {
    queryClient.setQueryData([...queryKey, page], context?.previousListing);
  };

  return {
    applyListingMutation,
    rollBackListingMutation
  };
};

export default useOptimisticListingMutation;
