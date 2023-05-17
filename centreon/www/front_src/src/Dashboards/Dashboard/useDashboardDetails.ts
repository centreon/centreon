import { useParams } from 'react-router-dom';

import { useFetchQuery } from '@centreon/ui';

import { dashboardsEndpoint } from '../api/endpoints';

import { dashboardDetailsDecoder } from './api/decoders';
import { DashboardDetails } from './models';

interface UseDashboardDetailsState {
  dashboard?: DashboardDetails;
}

const useDashboardDetails = (): UseDashboardDetailsState => {
  const { id } = useParams();

  const { data: dashboard } = useFetchQuery({
    decoder: dashboardDetailsDecoder,
    getEndpoint: () => `${dashboardsEndpoint}/${id}`,
    getQueryKey: () => ['dashboard', id]
  });

  return {
    dashboard
  };
};

export default useDashboardDetails;
