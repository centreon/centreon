// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import type { CounterProps, SelectEntry } from '@centreon/ui';
import { SeverityCode } from '@centreon/ui';

import getDefaultCriterias from '../../../Resources/Filter/Criterias/default';
import type { HostStatusResponse } from '../../api/decoders';
import {
  downCriterias,
  getHostResourcesUrl,
  hostCriterias,
  pendingCriterias,
  unhandledStateCriterias,
  unreachableCriterias,
  upCriterias
} from '../getResourcesUrl';
import type { Adapter } from '../useResourceCounters';
import { getNavigationFunction } from '../utils';
import {
  labelAll,
  labelDown,
  labelDownStatusHosts,
  labelHosts,
  labelPending,
  labelPendingStatusHosts,
  labelStatusCount,
  labelUnhandledOutOfTotal,
  labelUnreachable,
  labelUnreachableStatusHosts,
  labelUp,
  labelUpStatusHosts
} from './translatedLabels';

export interface HostPropsAdapterOutput {
  allLink: string;
  allOnClick: (e: React.MouseEvent<HTMLAnchorElement>) => void;
  buttonLabel: string;
  counters: CounterProps['counters'];
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
      label: t(labelDown),
      onClick: changeFilterAndNavigate({
        criterias: unhandledDownHostsCriterias,
        link: unhandledDownHostsLink
      }),
      severityCode: SeverityCode.High,
      shortCount: data.down.unhandled,
      to: unhandledDownHostsLink,
      tooltip: t(labelUnhandledOutOfTotal, {
        status: t(labelDown),
        total: data.down.total,
        unhandled: data.down.unhandled
      }),
      topCounterAriaLabel: t(labelDownStatusHosts)
    },
    pending: {
      label: t(labelPending),
      onClick: changeFilterAndNavigate({
        criterias: pendingHostsCriterias,
        link: pendingHostsLink
      }),
      severityCode: SeverityCode.Pending,
      shortCount: data.pending,
      to: pendingHostsLink,
      tooltip: t(labelStatusCount, {
        status: t(labelPending),
        total: data.pending
      }),
      topCounterAriaLabel: t(labelPendingStatusHosts)
    },
    unreachable: {
      label: t(labelUnreachable),
      onClick: changeFilterAndNavigate({
        criterias: unhandledUnreachableHostsCriterias,
        link: unhandledUnreachableHostsLink
      }),
      severityCode: SeverityCode.Medium,
      shortCount: data.unreachable.unhandled,
      to: unhandledUnreachableHostsLink,
      tooltip: t(labelUnhandledOutOfTotal, {
        status: t(labelUnreachable),
        total: data.unreachable.total,
        unhandled: data.unreachable.unhandled
      }),
      topCounterAriaLabel: t(labelUnreachableStatusHosts)
    },
    up: {
      label: t(labelUp),
      onClick: changeFilterAndNavigate({
        criterias: upHostsCriterias,
        link: upHostsLink
      }),
      severityCode: SeverityCode.OK,
      shortCount: data.ok,
      to: upHostsLink,
      tooltip: t(labelStatusCount, {
        status: t(labelUp),
        total: data.ok
      }),
      topCounterAriaLabel: t(labelUpStatusHosts)
    }
  };

  return {
    allLink: hostsLink,
    allOnClick: config.all.onClick,
    buttonLabel: t(labelHosts),
    counters: ['down', 'unreachable', 'up', 'pending'].map((statusName) => {
      const {
        to,
        shortCount,
        topCounterAriaLabel,
        onClick,
        severityCode,
        tooltip
      } = config[statusName as keyof typeof config];

      return {
        ariaLabel: topCounterAriaLabel,
        count: shortCount,
        detail: tooltip,
        onClick,
        severityCode,
        to
      };
    })
  };
};

export default getHostPropsAdapter;
