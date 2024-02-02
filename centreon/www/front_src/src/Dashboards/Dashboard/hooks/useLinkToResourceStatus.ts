import { includes } from 'ramda';

import {
  getResourcesUrlForMetricsWidgets,
  getUrlForResourcesOnlyWidgets,
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

    if (options?.statuses && options?.states && data?.resources) {
      const { resourceType: type, statuses, states } = options;
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

  return { getLinkToResourceStatusPage };
};

export default useLinkToResourceStatus;
