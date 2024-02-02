import { useEffect } from 'react';

import { useTokenListing } from './TokenListing/useTokenListing';

const useRefetch = (key: unknown): void => {
  const { refetch } = useTokenListing();

  useEffect(() => {
    if (!key) {
      return;
    }
    refetch();
  }, [key]);
};

export default useRefetch;
