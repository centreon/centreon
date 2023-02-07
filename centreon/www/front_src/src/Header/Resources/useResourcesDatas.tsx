import { useState, useMemo, useEffect } from 'react';

import { isNil, equals } from 'ramda';
import { useAtomValue, useUpdateAtom } from 'jotai/utils';
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

interface UseRessourcesCountersProps<Input, OutPut> {
  adapter: Adapter<Input, OutPut>;
  decoder: JsonDecoder.Decoder<Input>;
  endPoint: string;
  queryName: string;
}

interface UseRessourcesCountersOutput<OutPut> {
  data: OutPut;
  error: unknown;
  isAllowed: boolean;
  isLoading: boolean;
}

type UseRessourcesCounters = <Input extends Record<string, unknown>, OutPut>(
  params: UseRessourcesCountersProps<Input, OutPut>
) => UseRessourcesCountersOutput<OutPut>;

const useResourcesCounters: UseRessourcesCounters = ({
  endPoint,
  adapter,
  queryName,
  decoder
}) => {
  const applyFilter = useUpdateAtom(applyFilterDerivedAtom);
  const refetchInterval = useAtomValue(refreshIntervalAtom);
  const { use_deprecated_pages } = useAtomValue(userAtom);
  const navigate = useNavigate();
  const { t } = useTranslation();
  const [isAllowed, setIsAllowed] = useState<boolean>(true);
  const [datas, setDatas] = useState(null);

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

  useEffect(() => {
    if (!isNil(data)) {
      setDatas(
        adapter({
          applyFilter,
          data,
          navigate,
          t,
          useDeprecatedPages: use_deprecated_pages
        })
      );
    }
  }, [data]);

  return useMemo(
    () => ({ data: datas, isAllowed, isLoading }),
    [isLoading, datas]
  );
};

export default useResourcesCounters;
