import { SeverityCode } from '@centreon/ui';
import type { SelectEntry, SubMenuProps, CounterProps } from '@centreon/ui';

import {
  getHostResourcesUrl,
  upCriterias,
  unreachableCriterias,
  downCriterias,
  pendingCriterias,
  unhandledStateCriterias,
  hostCriterias
} from '../getResourcesUrl';
import getDefaultCriterias from '../../../Resources/Filter/Criterias/default';
import type { Adapter } from '../useResourceCounters';
import type { HostStatusResponse } from '../../api/decoders';
import {
  formatCount,
  formatUnhandledOverTotal,
  getNavigationFunction
} from '../utils';

import {
  labelDownStatusHosts,
  labelUnreachableStatusHosts,
  labelUpStatusHosts,
  labelAll,
  labelDown,
  labelPending,
  labelUnreachable,
  labelUp,
  labelHosts
} from './translatedLabels';

export interface HostPropsAdapterOutput {
  buttonLabel: string;
  counters: CounterProps['counters'];
  hasPending: boolean;
  items: SubMenuProps['items'];
}

type GetHostPropsAdapter = Adapter<HostStatusResponse, HostPropsAdapterOutput>;

const getHostPropsAdapter: GetHostPropsAdapter = ({
  useDeprecatedPages,
  applyFilter,
  navigate,
  t,
  data
}) => {
  const changeFilterAndNavigate = getNavigationFunction({
    applyFilter,
    navigate,
    useDeprecatedPages
  });

  const unhandledDownHostsCriterias = getDefaultCriterias({
    resourceTypes: hostCriterias.value,
    states: unhandledStateCriterias.value,
    statuses: downCriterias.value as Array<SelectEntry>
  });

  const unhandledDownHostsLink = useDeprecatedPages
    ? '/main.php?p=20202&o=h_down&search='
    : getHostResourcesUrl({
        stateCriterias: unhandledStateCriterias,
        statusCriterias: downCriterias
      });

  const unhandledUnreachableHostsCriterias = getDefaultCriterias({
    resourceTypes: hostCriterias.value,
    states: unhandledStateCriterias.value,
    statuses: unreachableCriterias.value as Array<SelectEntry>
  });
  const unhandledUnreachableHostsLink = useDeprecatedPages
    ? '/main.php?p=20202&o=h_unreachable&search='
    : getHostResourcesUrl({
        stateCriterias: unhandledStateCriterias,
        statusCriterias: unreachableCriterias
      });

  const upHostsCriterias = getDefaultCriterias({
    resourceTypes: hostCriterias.value,
    statuses: upCriterias.value as Array<SelectEntry>
  });
  const upHostsLink = useDeprecatedPages
    ? '/main.php?p=20202&o=h_up&search='
    : getHostResourcesUrl({
        statusCriterias: upCriterias
      });

  const hostsCriterias = getDefaultCriterias({
    resourceTypes: hostCriterias.value
  });
  const hostsLink = useDeprecatedPages
    ? '/main.php?p=20202&o=h&search='
    : getHostResourcesUrl();

  const pendingHostsCriterias = getDefaultCriterias({
    resourceTypes: hostCriterias.value,
    statuses: pendingCriterias.value as Array<SelectEntry>
  });
  const pendingHostsLink = useDeprecatedPages
    ? '/main.php?p=20202&o=h_pending&search='
    : getHostResourcesUrl({
        statusCriterias: pendingCriterias
      });

  const config = {
    all: {
      count: formatCount(data.total),
      label: t(labelAll),
      onClick: changeFilterAndNavigate({
        criterias: hostsCriterias,
        link: hostsLink
      }),
      serverityCode: null,
      shortCount: data.total,
      to: hostsLink
    },
    down: {
      count: formatUnhandledOverTotal(data.down.unhandled, data.down.total),
      label: t(labelDown),
      onClick: changeFilterAndNavigate({
        criterias: unhandledDownHostsCriterias,
        link: unhandledDownHostsLink
      }),
      severityCode: SeverityCode.High,
      shortCount: data.down.unhandled,
      to: unhandledDownHostsLink,
      topCounterAriaLabel: t(labelDownStatusHosts)
    },
    pending: {
      count: formatCount(data.pending),
      label: t(labelPending),
      onClick: changeFilterAndNavigate({
        criterias: pendingHostsCriterias,
        link: pendingHostsLink
      }),
      severityCode: SeverityCode.Pending,
      shortCount: data.pending,
      to: pendingHostsLink
    },
    unreachable: {
      count: formatUnhandledOverTotal(
        data.unreachable.unhandled,
        data.unreachable.total
      ),
      label: t(labelUnreachable),
      onClick: changeFilterAndNavigate({
        criterias: unhandledUnreachableHostsCriterias,
        link: unhandledUnreachableHostsLink
      }),
      severityCode: SeverityCode.Medium,
      shortCount: data.unreachable.unhandled,
      to: unhandledUnreachableHostsLink,
      topCounterAriaLabel: t(labelUnreachableStatusHosts)
    },
    up: {
      count: formatCount(data.ok),
      label: t(labelUp),
      onClick: changeFilterAndNavigate({
        criterias: upHostsCriterias,
        link: upHostsLink
      }),
      severityCode: SeverityCode.OK,
      shortCount: data.ok,
      to: upHostsLink,
      topCounterAriaLabel: t(labelUpStatusHosts)
    }
  };

  return {
    buttonLabel: t(labelHosts),
    counters: ['down', 'unreachable', 'up'].map((statusName) => {
      const { to, shortCount, topCounterAriaLabel, onClick, severityCode } =
        config[statusName];

      return {
        ariaLabel: topCounterAriaLabel,
        count: shortCount,
        onClick,
        severityCode,
        to
      };
    }),
    hasPending: Number(data.pending) > 0,
    items: ['down', 'unreachable', 'up', 'pending', 'all'].map((status) => {
      const { onClick, severityCode, count, label, to } = config[status];

      return {
        onClick,
        severityCode,
        submenuCount: count,
        submenuTitle: label,
        to
      };
    })
  };
};

export default getHostPropsAdapter;
