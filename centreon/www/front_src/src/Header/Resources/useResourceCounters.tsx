import { useState, useMemo } from 'react';

import { isNil, equals } from 'ramda';
import { useAtomValue, useSetAtom } from 'jotai';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import type { NavigateFunction } from 'react-router-dom';
import type { JsonDecoder } from 'ts.data.json';
import type { TFunction } from 'react-i18next';

import { useFetchQuery } from '@centreon/ui';
import { userAtom, refreshIntervalAtom } from '@centreon/ui-context';

import type { Filter } from '../../Resources/Filter/models';
import { applyFilterDerivedAtom } from '../../Resources/Filter/filterAtoms';

interface AdapterProps<Input> {
  applyFilter: (update: Filter) => void;
  data: Input;
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
  const applyFilter = useSetAtom(applyFilterDerivedAtom);
  const navigate = useNavigate();
  const { t } = useTranslation();
  const [isAllowed, setIsAllowed] = useState<boolean>(true);
  const refetchInterval = useAtomValue(refreshIntervalAtom);
  const { use_deprecated_pages } = useAtomValue(userAtom);

  const { isLoading, data } = useFetchQuery({
    catchError: ({ statusCode }): void => {
      if (equals(statusCode, 401)) {
        setIsAllowed(false);
      }
    },
    decoder,
    getEndpoint: () => endPoint,
    getQueryKey: () => [endPoint, queryName],
    queryOptions: {
      refetchInterval: refetchInterval * 1000
    }
  });

  return useMemo(
    () => ({
      data: !isNil(data)
        ? adapter({
            applyFilter,
            data,
            navigate,
            t,
            useDeprecatedPages: use_deprecated_pages
          })
        : null,
      isAllowed,
      isLoading
    }),
    [isLoading, data]
  );
};

export default useResourceCounters;
