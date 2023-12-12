import { useMemo } from 'react';

import { useAtomValue } from 'jotai';

import { searchAtom } from '../filterAtoms';

import { findFieldInformationFromSearchInput } from './utils';

interface SearchInputDataByField {
  content: string;
  fieldInformation: string;
}

interface Params {
  field: string;
}

const useSearchInputDataByField = ({
  field
}: Params): SearchInputDataByField => {
  const search = useAtomValue(searchAtom);

  const searchInputDataByField = useMemo((): {
    content: string;
    fieldInformation: string;
  } => {
    return findFieldInformationFromSearchInput({ field, search });
  }, [field, search]);

  return { ...searchInputDataByField };
};

export default useSearchInputDataByField;
