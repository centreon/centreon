import { equals, includes } from 'ramda';
import { useNavigate } from 'react-router';

import {
  getResourcesUrlForMetricsWidgets,
  getResourcesUrlForStatusGrid,
  openGraphPanel,
  resourceBasedWidgets
} from '../utils';

interface UseGoToResourceStatus {
  goToResourceStatus: (data, name, options) => void;
}

const useGoToResourceStatus = (): UseGoToResourceStatus => {
  const navigate = useNavigate();

  const goToResourceStatus = (data, name, options): void => {
    if (!includes(name, resourceBasedWidgets)) {
      return;
    }

    if (equals(name, 'centreon-widget-statusgrid')) {
      const { resourceType: type, statuses, states } = options;
      const { resources } = data;

      navigate(
        getResourcesUrlForStatusGrid({
          resources,
          states,
          statuses,
          type
        })
      );

      return;
    }

    navigate(getResourcesUrlForMetricsWidgets(data));

    if (equals(name, 'centreon-widget-singlemetric')) {
      openGraphPanel(data);
    }
  };

  return { goToResourceStatus };
};

export default useGoToResourceStatus;
