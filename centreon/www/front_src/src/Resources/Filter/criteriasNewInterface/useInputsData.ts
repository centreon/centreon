import { useEffect, useState } from 'react';

import { useSetAtom } from 'jotai';
import { equals } from 'ramda';

import {
  Criteria,
  CriteriaDisplayProps,
  SearchedDataValue as SearchedDataValueModel
} from '../Criterias/models';

import {
  displayActionsAtom,
  displayInformationFilterAtom
} from './basicFilter/atoms';
import { BasicCriteria, ExtendedCriteria } from './model';

interface Parameters {
  data: Array<Criteria & CriteriaDisplayProps>;
  filterName: BasicCriteria | ExtendedCriteria;
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

    const item = data.find(({ name }) => equals(name, filterName));

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
