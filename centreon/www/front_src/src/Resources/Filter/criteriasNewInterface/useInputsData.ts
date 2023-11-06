import { useEffect, useState } from 'react';

import { useSetAtom } from 'jotai';

import {
  Criteria,
  CriteriaDisplayProps,
  SearchedDataValue as SearchedDataValueModel
} from '../Criterias/models';

import { findData } from './utils';
import {
  displayActionsAtom,
  displayInformationFilterAtom
} from './basicFilter/atoms';

interface Parameters {
  data: Array<Criteria & CriteriaDisplayProps>;
  filterName: string;
}

type SearchedDataValue = SearchedDataValueModel | undefined | null;

interface UseInputData {
  dataByFilterName: (Criteria & CriteriaDisplayProps) | undefined;
  valueSearchData?: SearchedDataValue;
}

const useInputData = ({ data, filterName }: Parameters): UseInputData => {
  const [dataByFilterName, setDataByFilterName] = useState<
    undefined | (Criteria & CriteriaDisplayProps)
  >();

  const setDisplayActions = useSetAtom(displayActionsAtom);
  const setDisplayInformationFilter = useSetAtom(displayInformationFilterAtom);

  useEffect(() => {
    if (!data) {
      return;
    }

    const item = findData({ data, filterName });

    setDataByFilterName(item);
  }, [data]);

  useEffect(() => {
    if (!dataByFilterName) {
      return;
    }
    setDisplayInformationFilter(true);

    setDisplayActions(true);
  }, [dataByFilterName, filterName]);

  return { dataByFilterName };
};

export default useInputData;
