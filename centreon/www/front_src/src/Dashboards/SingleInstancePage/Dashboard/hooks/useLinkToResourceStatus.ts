import { equals } from 'ramda';
import { useSetAtom } from 'jotai';

import {
  getResourcesUrlForMetricsWidgets,
  getUrlForResourcesOnlyWidgets
} from '../utils';
import { selectedVisualizationAtom } from '../../../../Resources/Actions/actionsAtoms';
import { Visualization } from '../../../../Resources/models';
import { selectedColumnIdsAtom } from '../../../../Resources/Listing/listingAtoms';
import {
  defaultSelectedColumnIdsforViewByHost,
  defaultSelectedColumnIds
} from '../../../../Resources/Listing/columns';

interface UseLinkToResourceStatus {
  changeViewMode: (options) => void;
  getLinkToResourceStatusPage: (data, name, options) => string;
}

const useLinkToResourceStatus = (): UseLinkToResourceStatus => {
  const selectedVisualization = useSetAtom(selectedVisualizationAtom);

  const setSelectedColumnIds = useSetAtom(selectedColumnIdsAtom);

  const getLinkToResourceStatusPage = (data, name, options): string => {
    if (!data?.resources) {
      return '';
    }

    if (!data?.metrics) {
      const { statuses, states } = options;

      const type =
        options?.resourceType || options?.resourceTypes || options?.displayType;

      const { resources } = data;

      const linkToResourceStatus = getUrlForResourcesOnlyWidgets({
        resources,
        states,
        statuses,
        type
      });

      return linkToResourceStatus;
    }

    return getResourcesUrlForMetricsWidgets({ data, widgetName: name });
  };

  const changeViewMode = (displayType): void => {
    if (!displayType) {
      return;
    }

    if (equals(displayType, 'all')) {
      selectedVisualization(Visualization.All);

      setSelectedColumnIds(defaultSelectedColumnIds);
    }

    if (equals(displayType, 'service')) {
      selectedVisualization(Visualization.Service);

      setSelectedColumnIds(defaultSelectedColumnIds);
    }

    if (equals(displayType, 'host')) {
      setSelectedColumnIds(defaultSelectedColumnIdsforViewByHost);

      selectedVisualization(Visualization.Host);
    }
  };

  return { changeViewMode, getLinkToResourceStatusPage };
};

export default useLinkToResourceStatus;
