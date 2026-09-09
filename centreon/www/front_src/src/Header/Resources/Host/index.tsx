import {
  HostIcon,
  MenuSkeleton,
  TopCounterLayout,
  TopCounterResourceCounters
} from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import type { HostStatusResponse } from '../../api/decoders';
import { hostStatusDecoder } from '../../api/decoders';
import { hostStatusEndpoint } from '../../api/endpoints';
import useResourceCounters from '../useResourceCounters';
import type { HostPropsAdapterOutput } from './getHostPropsAdapter';
import getHostPropsAdapter from './getHostPropsAdapter';
import { labelHostsOverview } from './translatedLabels';

const HostStatusCounter = (): JSX.Element | null => {
  const { t } = useTranslation();
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
      iconLink={data.allLink}
      iconOnClick={data.allOnClick}
      renderIndicators={(): JSX.Element => (
        <TopCounterResourceCounters counters={data.counters} />
      )}
      title={data.buttonLabel}
      tooltipDescription={t(labelHostsOverview)}
    />
  );
};

export default HostStatusCounter;
