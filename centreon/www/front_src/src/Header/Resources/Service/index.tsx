import ServiceIcon from '@mui/icons-material/Grain';

import {
  MenuSkeleton,
  ItemLayout,
  ResourceCounters,
  ResourceSubMenu
} from '@centreon/ui';

import useResourceCounters from '../useResourceCounters';
import { serviceStatusDecoder } from '../../api/decoders';
import type { ServiceStatusResponse } from '../../api/decoders';
import { serviceStatusEndpoint } from '../../api/endpoints';

import type { ServicesPropsAdapterOutput } from './getServicePropsAdapter';
import getServicePropsAdapter from './getServicePropsAdapter';
import { labelServices } from './translatedLabels';

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
    return <MenuSkeleton width={20} />;
  }

  if (!isAllowed || !data) {
    return null;
  }

  return (
    <ItemLayout
      Icon={ServiceIcon}
      renderIndicators={(): JSX.Element => (
        <ResourceCounters counters={data.counters} />
      )}
      renderSubMenu={(): JSX.Element => <ResourceSubMenu items={data.items} />}
      showPendingBadge={data.hasPending}
      title={labelServices}
    />
  );
};

export default ServiceStatusCounter;
