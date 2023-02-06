// import { useQuery } from "@tanstack/react-query";
import { refreshIntervalAtom } from "@centreon/ui-context";
import { useAtomValue, useUpdateAtom } from "jotai/utils";
import { useState, useMemo, useEffect } from "react";
import { userAtom } from "@centreon/ui-context";
import { useNavigate } from "react-router-dom";
import { useTranslation } from "react-i18next";

import { applyFilterDerivedAtom } from "../../Resources/Filter/filterAtoms";
import { useFetchQuery } from "@centreon/ui";

import type { Filter } from "../../../Resources/Filter/models";
import type { NavigateFunction } from "react-router-dom";
import type { JsonDecoder } from "ts.data.json";
import type { TFunction } from "react-i18next";

interface AdapterProps<Input> {
  useDeprecatedPages: boolean;
  applyFilter: (update: Filter) => void;
  navigate: NavigateFunction;
  t: TFunction<"translation", undefined>;
  data: Input;
}

export type Adapter<Input, OutPut> = (params: AdapterProps<Input>) => OutPut;

interface UseRessourcesCountersProps<Input, OutPut> {
  endPoint: string;
  queryName: string;
  adapter: Adapter<Input, OutPut>;
  decoder: JsonDecoder.Decoder<Input>;
}

interface UseRessourcesCountersOutput<OutPut> {
  isLoading: boolean;
  error: unknown;
  data: OutPut;
  isAllowed: boolean;
}

type UseRessourcesCounters = <Input extends Object, OutPut>(
  params: UseRessourcesCountersProps<Input, OutPut>
) => UseRessourcesCountersOutput<OutPut>;

const useResourcesCounters: UseRessourcesCounters = ({
  endPoint,
  adapter,
  queryName,
  decoder,
}) => {
  const [isAllowed, setIsAllowed] = useState<boolean>(true);
  const [datas, setDatas] = useState(null);
  const refetchInterval = useAtomValue(refreshIntervalAtom);
  const { use_deprecated_pages } = useAtomValue(userAtom);
  const applyFilter = useUpdateAtom(applyFilterDerivedAtom);
  const navigate = useNavigate();
  const { t } = useTranslation();

  const { isLoading, error, data } = useFetchQuery({
    getQueryKey: () => [endPoint, queryName],
    getEndpoint: () => endPoint,
    decoder,
    queryOptions: {
      refetchInterval: refetchInterval * 1000, // refetchInterval from user or API response ?
    },
    catchError: ({ statusCode }) => {
      if (statusCode === 401) {
        setIsAllowed(false);
      }
    },
  });

  useEffect(() => {
    if (data) {
      setDatas(
        adapter({
          useDeprecatedPages: use_deprecated_pages,
          applyFilter,
          navigate,
          t,
          data,
        })
      );
    }
  }, [data]);

  return useMemo(
    () => ({ isLoading, error, data: datas, isAllowed }),
    [isLoading, error, datas]
  );
};

export default useResourcesCounters;
