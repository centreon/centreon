import { useSetAtom, useAtomValue } from 'jotai';
import { equals, has } from 'ramda';

import { selectedVisualizationAtom } from '../actionsAtoms';
import { Visualization } from '../../models';
import {
  searchAtom,
  setCriteriaAndNewFilterDerivedAtom
} from '../../Filter/filterAtoms';

import { platformVersionsAtom } from 'www/front_src/src/Main/atoms/platformVersionsAtom';

interface Props {
  type: Visualization;
}

interface State {
  selectVisualization: () => void;
}

const useVisualization = ({ type }: Props): State => {
  const platform = useAtomValue(platformVersionsAtom);
  const setVisualization = useSetAtom(selectedVisualizationAtom);
  const setSearch = useSetAtom(searchAtom);
  const setCriteriaAndNewFilter = useSetAtom(
    setCriteriaAndNewFilterDerivedAtom
  );

  const isAnomalyDetectionmModuleInstalled = has(
    'centreon-anomaly-detection',
    platform?.modules
  );

  const search = isAnomalyDetectionmModuleInstalled
    ? 'type:service,metaservice,anomaly-detection'
    : 'type:service,metaservice';

  const selectVisualization = (): void => {
    setVisualization(type);

    if (equals(type, Visualization.Service)) {
      setSearch(search);
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
