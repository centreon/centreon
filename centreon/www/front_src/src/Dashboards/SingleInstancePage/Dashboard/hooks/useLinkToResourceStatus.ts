import { all, equals, has, isNil, pluck } from 'ramda';
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
import {
  labelBusinessActivity,
  labelResourcesStatus
} from '../translatedLabels';

interface UseLinkToResourceStatus {
  changeViewMode: (options) => void;
  getLinkToResourceStatusPage: (data, name) => string;
  getPageType: (data) => string | null;
}

const useLinkToResourceStatus = (): UseLinkToResourceStatus => {
  const selectedVisualization = useSetAtom(selectedVisualizationAtom);
  const setSelectedColumnIds = useSetAtom(selectedColumnIdsAtom);

  const getLinkToResourceStatusPage = (data, name, options): string => {
    const resourcesInput = Object.entries(data).find(
      ([, value]) =>
        has('resourceType', value?.[0]) && has('resources', value?.[0])
    );
    const resourcesInputKey = resourcesInput?.[0];
    if (!resourcesInputKey || !data?.[resourcesInputKey]) {
      return '';
    }

    const resources = data[resourcesInputKey];
    // TO FIX when Resources Status will handle BA/BV properly
    const resourceTypes = pluck('resourceType', resources);
    const hasOnlyBA = all(equals('business-activity'), resourceTypes);

    if (hasOnlyBA) {
      return `/main.php?p=20701&o=d&ba_id=${resources[0].resources[0].id}`;
    }

    if (data?.resources && isNil(data?.metrics)) {
      const { statuses } = options;

      const linkToResourceStatus = getUrlForResourcesOnlyWidgets({
        resources: data.resources,
        states: options?.states || [],
        statuses,
        type:
          options?.resourceTypes ||
          options?.resourceType ||
          options?.displayType ||
          options?.type
      });

      return linkToResourceStatus;
    }

    return getResourcesUrlForMetricsWidgets({ data, widgetName: name });
  };

  const getPageType = (data): string | null => {
    if (isNil(data)) {
      return null;
    }
    const resourcesInput = Object.entries(data).find(
      ([, value]) =>
        has('resourceType', value?.[0]) && has('resources', value?.[0])
    );
    const resourcesInputKey = resourcesInput?.[0];
    if (!resourcesInputKey || !data?.[resourcesInputKey]) {
      return null;
    }

    const resources = data[resourcesInputKey];
    // TO FIX when Resources Status will handle BA/BV properly
    const resourceTypes = pluck('resourceType', resources);
    const hasOnlyBA = all(equals('business-activity'), resourceTypes);

    if (hasOnlyBA) {
      return labelBusinessActivity;
    }

    return labelResourcesStatus;
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

  return { changeViewMode, getLinkToResourceStatusPage, getPageType };
};

export default useLinkToResourceStatus;
