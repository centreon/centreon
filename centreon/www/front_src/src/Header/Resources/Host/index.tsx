import HostIcon from '@mui/icons-material/Dns';

import {
  MenuSkeleton,
  TopCounterLayout,
  TopCounterResourceCounters,
  TopCounterResourceSubMenu
} from '@centreon/ui';

import useResourceCounters from '../useResourceCounters';
import { hostStatusEndpoint } from '../../api/endpoints';
import { hostStatusDecoder } from '../../api/decoders';
import type { HostStatusResponse } from '../../api/decoders';

import getHostPropsAdapter from './getHostPropsAdapter';
import type { HostPropsAdapterOutput } from './getHostPropsAdapter';

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
    return <MenuSkeleton width={11} />;
  }

  if (!isAllowed || !data) {
    return null;
  }

  return (
    <TopCounterLayout
      Icon={HostIcon}
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

export default HostStatusCounter;
