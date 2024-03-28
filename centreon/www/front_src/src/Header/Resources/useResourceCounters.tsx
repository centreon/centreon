import { useMemo } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { isNil } from 'ramda';
import type { TFunction } from 'react-i18next';
import { useTranslation } from 'react-i18next';
import type { NavigateFunction } from 'react-router-dom';
import { useNavigate } from 'react-router-dom';
import type { JsonDecoder } from 'ts.data.json';

import { useFetchQuery } from '@centreon/ui';
import { refreshIntervalAtom, userAtom } from '@centreon/ui-context';

import { applyFilterDerivedAtom } from '../../Resources/Filter/filterAtoms';
import type { Filter } from '../../Resources/Filter/models';

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
  const navigate = useNavigate();
  const { t } = useTranslation();

  const refetchInterval = useAtomValue(refreshIntervalAtom);
  const { use_deprecated_pages } = useAtomValue(userAtom);
  const applyFilter = useSetAtom(applyFilterDerivedAtom);

  const { isLoading, data, error } = useFetchQuery({
    decoder,
    getEndpoint: () => endPoint,
    getQueryKey: () => [endPoint, queryName],
    httpCodesBypassErrorSnackbar: [401],
    queryOptions: {
      refetchInterval: refetchInterval * 1000
    }
  });

  return useMemo(() => {
    return {
      data: !isNil(data)
        ? adapter({
            applyFilter,
            data,
            navigate,
            t,
            useDeprecatedPages: use_deprecated_pages
          })
        : null,
      isAllowed: Boolean(data && isNil(error)),
      isLoading
    };
  }, [isLoading, data, error]);
};

export default useResourceCounters;
