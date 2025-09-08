import { useAtomValue } from 'jotai';
import { SelectEntry } from 'packages/ui/src';
import { prop } from 'ramda';
import { CriteriaValue } from '../../Filter/Criterias/models';
import { getCriteriaValueDerivedAtom } from '../../Filter/filterAtoms';

interface UseGetCriteriaNamesState {
  getCriteriaNames: (name: string) => Array<string | number> | undefined;
  getCriteriaIds: (name: string) => Array<string | number> | undefined;
  getCriteriaValue: (name: string) => CriteriaValue | undefined;
}

const useGetCriteriaName = (): UseGetCriteriaNamesState => {
  const getCriteriaValue = useAtomValue(getCriteriaValueDerivedAtom);

  const getCriteriaNames = (name: string): Array<string> => {
    const criteriaValue = getCriteriaValue(name) as
      | Array<SelectEntry>
      | undefined;

    return (criteriaValue || []).map(prop('name')) as Array<string>;
  };

  const getCriteriaIds = (name: string): Array<string | number> | undefined => {
    const criteriaValue = getCriteriaValue(name) as
      | Array<SelectEntry>
      | undefined;

    return criteriaValue?.map(prop('id'));
  };

  return { getCriteriaNames, getCriteriaValue, getCriteriaIds };
};

export default useGetCriteriaName;
