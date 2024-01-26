import { useMemo } from 'react';

import { useAtomValue } from 'jotai';
import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

import { useFetchQuery } from '@centreon/ui';
import { refreshIntervalAtom, userAtom } from '@centreon/ui-context';

import { pollerIssuesDecoder } from '../api/decoders';
import { pollerListIssuesEndPoint } from '../api/endpoints';

import type { GetPollerPropsAdapterResult } from './getPollerPropsAdapter';
import { getPollerPropsAdapter } from './getPollerPropsAdapter';

interface UsePollerDataResult {
  data: GetPollerPropsAdapterResult | null;
  isAllowed: boolean;
  isLoading: boolean;
}

export const usePollerData = (): UsePollerDataResult => {
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { isExportButtonEnabled } = useAtomValue(userAtom);
  const refetchInterval = useAtomValue(refreshIntervalAtom);

  const { isLoading, data, error } = useFetchQuery({
    decoder: pollerIssuesDecoder,
    getEndpoint: () => pollerListIssuesEndPoint,
    getQueryKey: () => [pollerListIssuesEndPoint, 'get-poller-status'],
    httpCodesBypassErrorSnackbar: [401],
    queryOptions: {
      refetchInterval: refetchInterval * 1000
    }
  });

  return useMemo(
    () => ({
      data: !isNil(data)
        ? getPollerPropsAdapter({
            data,
            isExportButtonEnabled,
            navigate,
            t
          })
        : null,
      isAllowed: Boolean(data && isNil(error)),
      isLoading
    }),
    [isLoading, data]
  );
};

export default usePollerData;
