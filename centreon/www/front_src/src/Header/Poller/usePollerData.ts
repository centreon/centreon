import { useMemo, useState } from 'react';

import { useAtomValue } from 'jotai';
import { equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

import { useFetchQuery } from '@centreon/ui';
import { refreshIntervalAtom, userAtom } from '@centreon/ui-context';

import { pollerIssuesDecoder } from '../api/decoders';
import { pollerListIssuesEndPoint } from '../api/endpoints';

import { getPollerPropsAdapter } from './getPollerPropsAdapter';
import type { GetPollerPropsAdapterResult } from './getPollerPropsAdapter';

interface UsePollerDataResult {
  data: GetPollerPropsAdapterResult | null;
  isAllowed: boolean;
  isLoading: boolean;
}

export const usePollerData = (): UsePollerDataResult => {
  const navigate = useNavigate();
  const { t } = useTranslation();
  const [isAllowed, setIsAllowed] = useState<boolean>(true);
  const { isExportButtonEnabled } = useAtomValue(userAtom);
  const refetchInterval = useAtomValue(refreshIntervalAtom);

  const { isLoading, data } = useFetchQuery({
    catchError: ({ statusCode }): void => {
      if (equals(statusCode, 401)) {
        setIsAllowed(false);
      }
    },
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
      isAllowed,
      isLoading
    }),
    [isLoading, data]
  );
};

export default usePollerData;
