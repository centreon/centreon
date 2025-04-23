import { useEffect } from 'react';

import { useTokenListing } from './TokenListing/useTokenListing';

interface UseRefetch {
  isRefetching: boolean;
}
interface Props {
  key: unknown;
  onSuccess?: () => void;
}

const useRefetch = ({ key, onSuccess }: Props): UseRefetch => {
  const { refetch, isRefetching } = useTokenListing({ enabled: false });

  useEffect(() => {
    if (!key) {
      return;
    }
    refetch().then(() => {
      onSuccess?.();
    });
  }, [key]);

  return { isRefetching };
};

export default useRefetch;
