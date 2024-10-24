import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { cond, equals, has } from 'ramda';

import { platformVersionsAtom } from '@centreon/ui-context';

import { CriteriaNames } from '../../Filter/Criterias/models';
import { setCriteriaAndNewFilterDerivedAtom } from '../../Filter/filterAtoms';
import { defaultSelectedColumnIdsforViewByHost } from '../../Listing/columns/index';
import { listingAtom, selectedColumnIdsAtom } from '../../Listing/listingAtoms';
import { ResourceListing, Visualization } from '../../models';
import { selectedVisualizationAtom } from '../actionsAtoms';

interface Props {
  type: Visualization;
}

interface State {
  selectVisualization: () => void;
}

const useVisualization = ({ type }: Props): State => {
  const [listingData, setListingData] = useAtom(listingAtom);
  const platform = useAtomValue(platformVersionsAtom);
  const setVisualization = useSetAtom(selectedVisualizationAtom);
  const setCriteriaAndNewFilter = useSetAtom(
    setCriteriaAndNewFilterDerivedAtom
  );
  const setSelectedColumnIds = useSetAtom(selectedColumnIdsAtom);

  const isAnomalyDetectionModuleInstalled = has(
    'centreon-anomaly-detection',
    platform?.modules
  );

  const searchValueForVisualizationByService = [
    { id: 'service', name: 'service' },
    { id: 'metaservice', name: 'metaservice' },
    ...(isAnomalyDetectionModuleInstalled
      ? [{ id: 'anomaly-detection', name: 'anomaly-detection' }]
      : [])
  ];

  const searchValue = cond([
    [
      equals(Visualization.Service),
      (): unknown => searchValueForVisualizationByService
    ],
    [equals(Visualization.Host), (): unknown => [{ id: 'host', name: 'host' }]],
    [equals(Visualization.All), () => []]
  ])(type);

  const nonAllSortValues = [
    'status_severity_code',
    'desc',
    'last_status_change',
    'desc'
  ];

  const sortValues = cond([
    [equals(Visualization.Service), () => nonAllSortValues],
    [equals(Visualization.Host), () => nonAllSortValues],
    [equals(Visualization.All), () => ['last_status_change', 'desc']]
  ])(type);

  const resetColumnsConfiguration = (): void => {
    if (equals(type, Visualization.Host)) {
      setSelectedColumnIds(defaultSelectedColumnIdsforViewByHost);

      return;
    }
  };

  const updateSearchAndSortValues = (): void => {
    setCriteriaAndNewFilter({
      apply: true,
      name: CriteriaNames.resourceTypes,
      value: searchValue
    });
    setCriteriaAndNewFilter({
      apply: true,
      name: 'sort',
      value: sortValues
    });
  };

  const initializeListingRows = (): void => {
    const emptyListingData = { ...listingData, result: [] };
    setListingData(emptyListingData as ResourceListing);
  };

  const selectVisualization = (): void => {
    initializeListingRows();
    setVisualization(type);
    resetColumnsConfiguration();
    updateSearchAndSortValues();
  };

  return { selectVisualization };
};

export default useVisualization;
