import { useFetchQuery } from '@centreon/ui';
import {
  refreshIntervalAtom,
  userAtom,
  userPermissionsAtom
} from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { isNil } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

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
  const { t, i18n } = useTranslation();

  const userPermissions = useAtomValue(userPermissionsAtom);
  const { isExportButtonEnabled } = useAtomValue(userAtom);
  const refetchInterval = useAtomValue(refreshIntervalAtom);

  const isAllowed = useMemo(
    () => userPermissions?.poller_statistics || false,
    [userPermissions?.poller_statistics]
  );

  const { isLoading, data } = useFetchQuery({
    decoder: pollerIssuesDecoder,
    getEndpoint: () => pollerListIssuesEndPoint,
    getQueryKey: () => [pollerListIssuesEndPoint, 'get-poller-status'],
    httpCodesBypassErrorSnackbar: [401],
    queryOptions: {
      refetchInterval: refetchInterval * 1000,
      enabled: isAllowed,
      suspense: false
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
    [isLoading, data, i18n.language]
  );
};

export default usePollerData;
