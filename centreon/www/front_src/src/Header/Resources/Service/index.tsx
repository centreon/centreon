import ServiceIcon from '@mui/icons-material/Grain';

import {
  MenuSkeleton,
  TopCounterLayout,
  TopCounterResourceCounters,
  TopCounterResourceSubMenu
} from '@centreon/ui';

import useResourceCounters from '../useResourceCounters';
import { serviceStatusDecoder } from '../../api/decoders';
import type { ServiceStatusResponse } from '../../api/decoders';
import { serviceStatusEndpoint } from '../../api/endpoints';

import type { ServicesPropsAdapterOutput } from './getServicePropsAdapter';
import getServicePropsAdapter from './getServicePropsAdapter';

const ServiceStatusCounter = (): JSX.Element | null => {
  const { isLoading, data, isAllowed } = useResourceCounters<
    ServiceStatusResponse,
    ServicesPropsAdapterOutput
  >({
    adapter: getServicePropsAdapter,
    decoder: serviceStatusDecoder,
    endPoint: serviceStatusEndpoint,
    queryName: 'services-counters'
  });

  if (isLoading) {
    return <MenuSkeleton width={14} />;
  }

  if (!isAllowed || !data) {
    return null;
  }

  return (
    <TopCounterLayout
      Icon={ServiceIcon}
      renderIndicators={(): JSX.Element => (
        <TopCounterResourceCounters counters={data.counters} />
      )}
      renderSubMenu={(): JSX.Element => (
        <TopCounterResourceSubMenu items={data.items} />
      )}
      showPendingBadge={data.hasPending}
      title={data.buttonLabel}
    />
  );
};

export default ServiceStatusCounter;
