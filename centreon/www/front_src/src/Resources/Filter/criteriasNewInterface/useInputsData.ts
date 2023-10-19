import { useEffect, useState } from 'react';

import { useSetAtom } from 'jotai';

import {
  Criteria,
  CriteriaDisplayProps,
  SearchedDataValue as SearchedDataValueModel
} from '../Criterias/models';

import { ExtendedCriteria, SectionType } from './model';
import { findData } from './utils';
import {
  displayActionsAtom,
  displayInformationFilterAtom
} from './basicFilter/atoms';

interface Parameters {
  data: Array<Criteria & CriteriaDisplayProps>;
  filterName: string;
  resourceType?: SectionType;
}

type SearchedDataValue = SearchedDataValueModel | undefined | null;
interface UseInputData {
  dataByFilterName: (Criteria & CriteriaDisplayProps) | undefined;
  valueSearchData?: SearchedDataValue;
}

const useInputData = ({
  data,
  filterName,
  resourceType
}: Parameters): UseInputData => {
  const [dataByFilterName, setDataByFilterName] = useState<
    undefined | (Criteria & CriteriaDisplayProps)
  >();
  const [valueSearchData, setValueSearchData] = useState<SearchedDataValue>();

  const setDisplayActions = useSetAtom(displayActionsAtom);
  const setDisplayInformationFilter = useSetAtom(displayInformationFilterAtom);

  useEffect(() => {
    if (!data) {
      return;
    }
    const item = findData({ data, filterName });

    setDataByFilterName(item);

    if (!resourceType) {
      return;
    }
    const currentValueSearchData = item?.search_data?.values?.filter(
      (element) => element.id === resourceType
    );

    setValueSearchData(currentValueSearchData as SearchedDataValue);
  }, [data]);

  useEffect(() => {
    if (!dataByFilterName) {
      return;
    }
    if (
      !Object.values(ExtendedCriteria).includes(filterName as ExtendedCriteria)
    ) {
      return;
    }
    setDisplayInformationFilter(true);

    setDisplayActions(true);
  }, [dataByFilterName, filterName]);

  return { dataByFilterName, valueSearchData };
};

export default useInputData;
