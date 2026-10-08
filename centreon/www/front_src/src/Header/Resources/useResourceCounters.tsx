// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { useFetchQuery } from '@centreon/ui';
import {
  platformVersionsAtom,
  statisticsRefreshIntervalAtom,
  userAtom,
  userPermissionsAtom
} from '@centreon/ui-context';

import { useAtomValue, useSetAtom } from 'jotai';
import { isNil, keys } from 'ramda';
import { useMemo } from 'react';
import type { TFunction } from 'react-i18next';
import { useTranslation } from 'react-i18next';
import type { NavigateFunction } from 'react-router';
import { useNavigate } from 'react-router';
import type { JsonDecoder } from 'ts.data.json';

import { applyFilterDerivedAtom } from '../../Resources/Filter/filterAtoms';
import type { Filter } from '../../Resources/Filter/models';

interface AdapterProps<Input> {
  applyFilter: (update: Filter) => void;
  data: Input;
  installedModules: Array<string>;
  navigate: NavigateFunction;
  t: TFunction<'translation', undefined>;
  useDeprecatedPages: boolean;
}

export type Adapter<Input, OutPut> = (params: AdapterProps<Input>) => OutPut;

interface UseRessourceCountersProps<Input, OutPut> {
  adapter: Adapter<Input, OutPut>;
  decoder: JsonDecoder.Decoder<Input>;
  endPoint: string;
  queryName: string;
}

interface UseRessourceCountersOutput<OutPut> {
  data: OutPut | null;
  isAllowed: boolean;
  isLoading: boolean;
}

type UseRessourceCounters = <Input extends Record<string, unknown>, OutPut>(
  params: UseRessourceCountersProps<Input, OutPut>
) => UseRessourceCountersOutput<OutPut>;

const useResourceCounters: UseRessourceCounters = ({
  endPoint,
  adapter,
  queryName,
  decoder
}) => {
  const navigate = useNavigate();
  const { t, i18n } = useTranslation();

  const userPermissions = useAtomValue(userPermissionsAtom);

  const refetchInterval = useAtomValue(statisticsRefreshIntervalAtom);
  const { use_deprecated_pages } = useAtomValue(userAtom);
  const applyFilter = useSetAtom(applyFilterDerivedAtom);
  const platformVersions = useAtomValue(platformVersionsAtom);

  const installedModules = useMemo(
    () => keys(platformVersions?.modules ?? {}) as Array<string>,
    [platformVersions?.modules]
  );

  const isAllowed = useMemo(
    () => userPermissions?.top_counter || false,
    [userPermissions?.top_counter]
  );

  const { isLoading, data } = useFetchQuery({
    decoder,
    getEndpoint: () => endPoint,
    getQueryKey: () => [endPoint, queryName],
    httpCodesBypassErrorSnackbar: [401],
    queryOptions: {
      enabled: isAllowed,
      refetchInterval: refetchInterval * 1000,
      refetchOnMount: false,
      suspense: false
    }
  });

  return useMemo(
    () => ({
      data: !isNil(data)
        ? adapter({
            applyFilter,
            data,
            installedModules,
            navigate,
            t,
            useDeprecatedPages: use_deprecated_pages
          })
        : null,
      isAllowed,
      isLoading
    }),
    [isLoading, data, i18n.language, installedModules]
  );
};

export default useResourceCounters;
