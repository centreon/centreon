import { equals, includes } from 'ramda';

import {
  getResourcesUrlForMetricsWidgets,
  getResourcesUrlForStatusGrid,
  resourceBasedWidgets
} from '../utils';

interface UseLinkToResourceStatus {
  getLinkToResourceStatusPage: (data, name, options) => string;
}

const useLinkToResourceStatus = (): UseLinkToResourceStatus => {
  const getLinkToResourceStatusPage = (data, name, options): string => {
    if (!includes(name, resourceBasedWidgets)) {
      return '';
    }

    if (equals(name, 'centreon-widget-statusgrid')) {
      const { resourceType: type, statuses, states } = options;
      const { resources } = data;

      const linkToResourceStatus = getResourcesUrlForStatusGrid({
        resources,
        states,
        statuses,
        type
      });

      return linkToResourceStatus;
    }

    return getResourcesUrlForMetricsWidgets({ data, widgetName: name });
  };

  return { getLinkToResourceStatusPage };
};

export default useLinkToResourceStatus;
