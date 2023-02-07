import { withTranslation } from 'react-i18next';

import HostIcon from '@mui/icons-material/Dns';

import { MenuSkeleton } from '@centreon/ui';

import ItemLayout from '../../sharedUI/ItemLayout';
import ResourceCounters from '../../sharedUI/ResourceCounters';
import ResourceSubMenu from '../../sharedUI/ResourceSubMenu';
import useResourcesCounters from '../useResourcesDatas';
import { hostStatusEndpoint } from '../../api/endpoints';
import { hostStatusDecoder } from '../../api/decoders';

import getHostPropsAdapter from './getHostPropsAdapter';

const HostStatusCounter = (): JSX.Element | null => {
  const { isLoading, data, isAllowed } = useResourcesCounters({
    adapter: getHostPropsAdapter,
    decoder: hostStatusDecoder,
    endPoint: hostStatusEndpoint,
    queryName: 'hosts-counters'
  });

  if (!isAllowed) {
    return null;
  }

  if (isLoading) {
    return <MenuSkeleton width={20} />;
  }

  return (
    data && (
      <ItemLayout
        Icon={HostIcon}
        renderIndicators={(): JSX.Element => (
          <ResourceCounters counters={data.counters} />
        )}
        renderSubMenu={(): JSX.Element => (
          <ResourceSubMenu items={data.items} />
        )}
        showPendingBadge={data.hasPending}
        testId="Hosts"
        title="Hosts"
      />
    )
  );
};

export default withTranslation()(HostStatusCounter);
