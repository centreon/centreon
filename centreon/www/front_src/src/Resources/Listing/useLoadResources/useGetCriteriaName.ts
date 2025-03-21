import { useAtomValue } from 'jotai';
import { SelectEntry } from 'packages/ui/src';
import { prop } from 'ramda';
import { getCriteriaValueDerivedAtom } from '../../Filter/filterAtoms';

const useGetCriteriaName = () => {
  const getCriteriaValue = useAtomValue(getCriteriaValueDerivedAtom);

  const getCriteriaNames = (name: string): Array<string> => {
    const criteriaValue = getCriteriaValue(name) as
      | Array<SelectEntry>
      | undefined;

    return (criteriaValue || []).map(prop('name')) as Array<string>;
  };

  return { getCriteriaNames, getCriteriaValue };
};

export default useGetCriteriaName;
