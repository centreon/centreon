import HostIcon from '@mui/icons-material/Dns';

import {
  MenuSkeleton,
  ItemLayout,
  ResourceCounters,
  ResourceSubMenu
} from '@centreon/ui';

import useResourceCounters from '../useResourceCounters';
import { hostStatusEndpoint } from '../../api/endpoints';
import { hostStatusDecoder } from '../../api/decoders';
import type { HostStatusResponse } from '../../api/decoders';

import getHostPropsAdapter from './getHostPropsAdapter';
import type { HostPropsAdapterOutput } from './getHostPropsAdapter';
import { labelHosts } from './translatedLabels';

const HostStatusCounter = (): JSX.Element | null => {
  const { isLoading, data, isAllowed } = useResourceCounters<
    HostStatusResponse,
    HostPropsAdapterOutput
  >({
    adapter: getHostPropsAdapter,
    decoder: hostStatusDecoder,
    endPoint: hostStatusEndpoint,
    queryName: 'hosts-counters'
  });

  if (isLoading) {
    return <MenuSkeleton width={20} />;
  }

  if (!isAllowed || !data) {
    return null;
  }

  return (
    <ItemLayout
      Icon={HostIcon}
      renderIndicators={(): JSX.Element => (
        <ResourceCounters counters={data.counters} />
      )}
      renderSubMenu={(): JSX.Element => <ResourceSubMenu items={data.items} />}
      showPendingBadge={data.hasPending}
      title={labelHosts}
    />
  );
};

export default HostStatusCounter;
