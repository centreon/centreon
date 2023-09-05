import { useSetAtom } from 'jotai';
import { equals } from 'ramda';

import { selectedVisualizationAtom } from '../actionsAtoms';
import { Visualization } from '../../models';
import {
  searchAtom,
  setCriteriaAndNewFilterDerivedAtom
} from '../../Filter/filterAtoms';

interface Props {
  type: Visualization;
}

interface State {
  selectVisualization: () => void;
}

const useVisualization = ({ type }: Props): State => {
  const setVisualization = useSetAtom(selectedVisualizationAtom);
  const setSearch = useSetAtom(searchAtom);
  const setCriteriaAndNewFilter = useSetAtom(
    setCriteriaAndNewFilterDerivedAtom
  );

  const selectVisualization = (): void => {
    setVisualization(type);

    if (equals(type, Visualization.Service)) {
      setSearch('type:service,metaservice');
      setCriteriaAndNewFilter({
        apply: true,
        name: 'sort',
        value: ['status_severity_code', 'desc', 'last_status_change', 'desc']
      });
    }
    if (equals(type, Visualization.All)) {
      setSearch('');
      setCriteriaAndNewFilter({
        apply: true,
        name: 'sort',
        value: ['last_status_change', 'desc']
      });
    }
  };

  return { selectVisualization };
};

export default useVisualization;
