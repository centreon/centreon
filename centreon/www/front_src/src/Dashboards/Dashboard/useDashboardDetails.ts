import { useParams } from 'react-router-dom';

import { useFetchQuery } from '@centreon/ui';

import { dashboardsEndpoint } from '../api/endpoints';

import { dashboardDetailsDecoder } from './api/decoders';
import { DashboardDetails } from './models';

interface UseDashboardDetailsState {
  dashboard?: DashboardDetails;
}

const useDashboardDetails = (): UseDashboardDetailsState => {
  const { dashboardId } = useParams();

  const { data: dashboard } = useFetchQuery({
    decoder: dashboardDetailsDecoder,
    getEndpoint: () => `${dashboardsEndpoint}/${dashboardId}`,
    getQueryKey: () => ['dashboard', dashboardId]
  });

  return {
    dashboard
  };
};

export default useDashboardDetails;
