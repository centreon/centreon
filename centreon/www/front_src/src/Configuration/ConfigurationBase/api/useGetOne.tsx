import { useFetchQuery } from '@centreon/ui';

import { useAtomValue } from 'jotai';
import { isNotNil } from 'ramda';

import { configurationAtom } from '../atoms';

const useGetDetails = ({ id }: { id: number | null }) => {
  const configuration = useAtomValue(configurationAtom);

  const resourceType = configuration?.resourceType;
  const endpoint = configuration?.api?.endpoints?.getOne;
  const decoder = configuration?.api?.decoders?.getOne;

  const { data, isFetching } = useFetchQuery<object>({
    decoder: decoder as
      | import('ts.data.json').JsonDecoder.Decoder<object>
      | undefined,
    getEndpoint: () => endpoint?.({ id: id as number | string }) as string,
    getQueryKey: () => ['getDetails', id, resourceType],
    queryOptions: {
      enabled: isNotNil(id),
      suspense: false
    }
  });

  return { data, isLoading: isFetching };
};

export default useGetDetails;
