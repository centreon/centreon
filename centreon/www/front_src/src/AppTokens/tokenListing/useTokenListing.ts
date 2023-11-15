import { useState, useEffect } from 'react';

import { useFetchQuery } from '@centreon/ui';

import { listTokensDecoder } from '../api/decoder';
import { buildListTokensEndpoint } from '../api/endpoints';
import { defaultParameters } from '../api/models';

import { UseTokenListing } from './models';

export const useTokenListing = (): UseTokenListing => {
  const [pageListing, setPageListing] = useState(defaultParameters.page);
  const [limitListing, setLimitListing] = useState(defaultParameters.limit);
  const [rows, setRows] = useState();
  const { data, isLoading } = useFetchQuery({
    decoder: listTokensDecoder,
    getEndpoint: () => buildListTokensEndpoint(defaultParameters),
    getQueryKey: () => ['listTokens']
  });

  useEffect(() => {
    if (!data) {
      return;
    }
    const {
      meta: { page, limit }
    } = data;

    setPageListing(page);
    setLimitListing(limit);
    setRows(data.result);
  }, [data]);

  return { isLoading, limitListing, pageListing, rows };
};
