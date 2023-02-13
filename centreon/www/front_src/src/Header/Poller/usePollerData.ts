import { useState, useMemo } from 'react';

import { useNavigate } from 'react-router-dom';
import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import { equals, isNil } from 'ramda';

import { useFetchQuery } from '@centreon/ui';
import { refreshIntervalAtom, userAtom } from '@centreon/ui-context';

import useNavigation from '../../Navigation/useNavigation';
import { pollerListIssuesEndPoint } from '../api/endpoints';
import { pollerIssuesDecoder } from '../api/decoders';

import { getPollerPropsAdapter } from './getPollerPropsAdapter';
import type { GetPollerPropsAdapterResult } from './getPollerPropsAdapter';

interface UsePollerDataResult {
  data: GetPollerPropsAdapterResult | null;
  error: unknown;
  isAllowed: boolean;
  isLoading: boolean;
}

export const usePollerData = (): UsePollerDataResult => {
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { allowedPages } = useNavigation();
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
    queryOptions: {
      refetchInterval: refetchInterval * 1000
    }
  });

  return useMemo(
    () => ({
      data: !isNil(data)
        ? getPollerPropsAdapter({
            allowedPages,
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
