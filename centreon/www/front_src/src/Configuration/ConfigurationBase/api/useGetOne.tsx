import { useFetchQuery } from '@centreon/ui';
import { useAtomValue } from 'jotai';
import { isNotNil } from 'ramda';
import { configurationAtom } from '../atoms';

const useGetDetails = ({ id }: { id: number | null }) => {
  const configuration = useAtomValue(configurationAtom);

  const resourceType = configuration?.resourceType;
  const endpoint = configuration?.api?.endpoints?.getOne;
  const decoder = configuration?.api?.decoders?.getOne;

  const { data, isFetching } = useFetchQuery({
    decoder,
    getEndpoint: () => endpoint?.({ id }) as string,
    getQueryKey: () => ['getDetails', id, resourceType],
    queryOptions: {
      enabled: isNotNil(id),
      suspense: false
    }
  });

  return { data, isLoading: isFetching };
};

export default useGetDetails;
