import { useFetchQuery } from '@centreon/ui';

const useGetToken = ({ endpoint, queryKey }) => {
  const { fetchQuery, isFetching } = useFetchQuery({
    getEndpoint: () => endpoint,
    getQueryKey: () => queryKey,
    queryOptions: {
      enabled: false,
      suspense: false
    }
  });

  const getDetails = async () => {
    const data = await fetchQuery();

    return data;
  };

  return { getDetails, isLoading: isFetching };
};

export default useGetToken;
