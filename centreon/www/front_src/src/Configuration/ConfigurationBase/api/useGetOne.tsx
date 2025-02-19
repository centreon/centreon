import { useFetchQuery } from '@centreon/ui';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { configurationAtom } from '../../atoms';

interface UseGetOneProps {
  id: number;
  variant: 'create' | 'update';
}

const useGetOne = ({ id, variant }: UseGetOneProps) => {
  const configuration = useAtomValue(configurationAtom);

  const endpoint = configuration?.api?.endpoints?.getOne;
  const decoder = configuration?.api?.decoders?.getOne;

  const { data, isFetching } = useFetchQuery({
    decoder,
    getEndpoint: () => endpoint({ id }),
    getQueryKey: () => ['getHostGroup', id],
    queryOptions: {
      enabled: equals(variant, 'update'),
      suspense: false
    }
  });

  return { data, isLoading: isFetching };
};

export default useGetOne;
