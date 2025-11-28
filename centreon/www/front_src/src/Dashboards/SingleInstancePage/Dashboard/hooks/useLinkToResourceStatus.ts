import { useSetAtom } from 'jotai';
import { all, equals, has, isNil, pluck } from 'ramda';

import { selectedVisualizationAtom } from '../../../../Resources/Actions/actionsAtoms';
import {
  defaultSelectedColumnIds,
  defaultSelectedColumnIdsforViewByHost
} from '../../../../Resources/Listing/columns';
import { selectedColumnIdsAtom } from '../../../../Resources/Listing/listingAtoms';
import { Visualization } from '../../../../Resources/models';
import { areResourcesFullfilled } from '../Widgets/utils';
import {
  labelBusinessActivity,
  labelBusinessView,
  labelResourcesStatus
} from '../translatedLabels';
import {
  getResourcesUrlForMetricsWidgets,
  getUrlForResourcesOnlyWidgets
} from '../utils';

interface UseLinkToResourceStatus {
  changeViewMode: (options) => void;
  getLinkToResourceStatusPage: (data, name, options?) => string;
  getPageType: (data) => string | null;
}

const useLinkToResourceStatus = (): UseLinkToResourceStatus => {
  const selectedVisualization = useSetAtom(selectedVisualizationAtom);
  const setSelectedColumnIds = useSetAtom(selectedColumnIdsAtom);

  const getLinkToResourceStatusPage = (data, name, options?): string => {
    if (isNil(data)) return '';
    const resourcesInput = Object.entries(data).find(
      ([, value]) =>
        has('resourceType', value?.[0]) && has('resources', value?.[0])
    );
    const resourcesInputKey = resourcesInput?.[0];

    const hasInvalidResources =
      !resourcesInputKey ||
      !data?.[resourcesInputKey] ||
      !areResourcesFullfilled(data?.[resourcesInputKey]);

    if (hasInvalidResources) {
      return '';
    }

    const resources = data[resourcesInputKey];

    // TO FIX when Resources Status will handle BA/BV properly
    const resourceTypes = pluck('resourceType', resources);
    const hasOnlyBA = all(equals('business-activity'), resourceTypes);
    const hasOnlyBV = all(equals('business-view'), resourceTypes);

    if (hasOnlyBV) {
      const id = resources?.[0]?.resources?.[0]?.id;
      return id ? `/main.php?p=20701&status=all&bv_id=${id}` : '';
    }

    if (hasOnlyBA) {
      const id = resources?.[0]?.resources?.[0]?.id;
      return id ? `/monitoring/bam/bas/${id}` : '';
    }

    if (data[resourcesInputKey] && isNil(data?.metrics)) {
      const statuses = options?.statuses ?? [];

      const linkToResourceStatus = getUrlForResourcesOnlyWidgets({
        resources: resources,
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
    const hasOnlyBV = all(equals('business-view'), resourceTypes);

    if (hasOnlyBV) {
      return labelBusinessView;
    }

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
