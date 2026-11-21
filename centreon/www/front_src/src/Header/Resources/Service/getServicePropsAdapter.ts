// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import type { CounterProps, SelectEntry } from '@centreon/ui';
import { SeverityCode } from '@centreon/ui';

import getDefaultCriterias from '../../../Resources/Filter/Criterias/default';
import type { ServiceStatusResponse } from '../../api/decoders';
import {
  criticalCriterias,
  getResourcesUrl,
  getServiceCriterias,
  okCriterias,
  pendingCriterias,
  unhandledStateCriterias,
  unknownCriterias,
  warningCriterias
} from '../getResourcesUrl';
import type { Adapter } from '../useResourceCounters';
import { getNavigationFunction } from '../utils';
import {
  labelAll,
  labelCritical,
  labelCriticalStatusServices,
  labelOk,
  labelOkStatusServices,
  labelPending,
  labelPendingStatusServices,
  labelServices,
  labelStatusCount,
  labelUnhandledOutOfTotal,
  labelUnknown,
  labelUnknownStatusServices,
  labelWarning,
  labelWarningStatusServices
} from './translatedLabels';

export interface ServicesPropsAdapterOutput {
  allLink: string;
  allOnClick: (e: React.MouseEvent<HTMLAnchorElement>) => void;
  buttonLabel: string;
  counters: CounterProps['counters'];
}

type GetServicePropsAdapter = Adapter<
  ServiceStatusResponse,
  ServicesPropsAdapterOutput
>;

const getServicePropsAdapter: GetServicePropsAdapter = ({
  useDeprecatedPages,
  applyFilter,
  navigate,
  t,
  data,
  installedModules
}) => {
  const resourceTypeCriterias = getServiceCriterias({
    isAnomalyDetectionInstalled: installedModules.includes(
      'centreon-anomaly-detection'
    )
  });
  const stateCriterias = { name: 'states', value: [] };

  const allStatusesServicesCriterias = [
    ...okCriterias.value,
    ...pendingCriterias.value,
    ...unknownCriterias.value,
    ...warningCriterias.value,
    ...criticalCriterias.value
  ] as Array<SelectEntry>;

  const changeFilterAndNavigate = getNavigationFunction({
    applyFilter,
    navigate,
    useDeprecatedPages
  });

  const unhandledCriticalServicesCriterias = getDefaultCriterias({
    resourceTypes: resourceTypeCriterias.value,
    states: unhandledStateCriterias.value,
    statuses: criticalCriterias.value as Array<SelectEntry>
  });

  const unhandledCriticalServicesLink = useDeprecatedPages
    ? '/main.php?p=20201&o=svc_unhandled&statusFilter=critical&search='
    : getResourcesUrl({
        resourceTypeCriterias,
        stateCriterias: unhandledStateCriterias,
        statusCriterias: criticalCriterias
      });

  const unhandledWarningServicesCriterias = getDefaultCriterias({
    resourceTypes: resourceTypeCriterias.value,
    states: unhandledStateCriterias.value,
    statuses: warningCriterias.value as Array<SelectEntry>
  });

  const unhandledWarningServicesLink = useDeprecatedPages
    ? '/main.php?p=20201&o=svc_unhandled&statusFilter=warning&search='
    : getResourcesUrl({
        resourceTypeCriterias,
        stateCriterias: unhandledStateCriterias,
        statusCriterias: warningCriterias
      });

  const unhandledUnknownServicesCriterias = getDefaultCriterias({
    resourceTypes: resourceTypeCriterias.value,
    states: unhandledStateCriterias.value,
    statuses: unknownCriterias.value as Array<SelectEntry>
  });

  const unhandledUnknownServicesLink = useDeprecatedPages
    ? '/main.php?p=20201&o=svc_unhandled&statusFilter=unknown&search='
    : getResourcesUrl({
        resourceTypeCriterias,
        stateCriterias: unhandledStateCriterias,
        statusCriterias: unknownCriterias
      });

  const okServicesCriterias = getDefaultCriterias({
    resourceTypes: resourceTypeCriterias.value,
    statuses: okCriterias.value as Array<SelectEntry>
  });

  const okServicesLink = useDeprecatedPages
    ? '/main.php?p=20201&o=svc&statusFilter=ok&search='
    : getResourcesUrl({
        resourceTypeCriterias,
        stateCriterias,
        statusCriterias: okCriterias
      });

  const servicesCriterias = getDefaultCriterias({
    resourceTypes: resourceTypeCriterias.value,
    statuses: allStatusesServicesCriterias
  });

  const servicesLink = useDeprecatedPages
    ? '/main.php?p=20201&o=svc&statusFilter=&search='
    : getResourcesUrl({
        resourceTypeCriterias,
        stateCriterias,
        statusCriterias: {
          name: 'statuses',
          value: allStatusesServicesCriterias
        }
      });
  const pendingServicesCriterias = getDefaultCriterias({
    resourceTypes: resourceTypeCriterias.value,
    statuses: pendingCriterias.value as Array<SelectEntry>
  });

  const pendingServicesLink = useDeprecatedPages
    ? '/main.php?p=20201&o=svc&statusFilter=pending&search='
    : getResourcesUrl({
        resourceTypeCriterias,
        stateCriterias,
        statusCriterias: pendingCriterias
      });

  const config = {
    all: {
      label: t(labelAll),
      onClick: changeFilterAndNavigate({
        criterias: servicesCriterias,
        link: servicesLink
      }),
      shortCount: data.total,
      to: servicesLink
    },
    critical: {
      label: t(labelCritical),
      onClick: changeFilterAndNavigate({
        criterias: unhandledCriticalServicesCriterias,
        link: unhandledCriticalServicesLink
      }),
      severityCode: SeverityCode.High,
      shortCount: data.critical.unhandled,
      to: unhandledCriticalServicesLink,
      tooltip: t(labelUnhandledOutOfTotal, {
        status: t(labelCritical),
        total: data.critical.total,
        unhandled: data.critical.unhandled
      }),
      topCounterAriaLabel: t(labelCriticalStatusServices)
    },
    ok: {
      label: t(labelOk),
      onClick: changeFilterAndNavigate({
        criterias: okServicesCriterias,
        link: okServicesLink
      }),
      severityCode: SeverityCode.OK,
      shortCount: data.ok,
      to: okServicesLink,
      tooltip: t(labelStatusCount, {
        status: t(labelOk),
        total: data.ok
      }),
      topCounterAriaLabel: t(labelOkStatusServices)
    },
    pending: {
      label: t(labelPending),
      onClick: changeFilterAndNavigate({
        criterias: pendingServicesCriterias,
        link: pendingServicesLink
      }),
      severityCode: SeverityCode.Pending,
      shortCount: data.pending,
      to: pendingServicesLink,
      tooltip: t(labelStatusCount, {
        status: t(labelPending),
        total: data.pending
      }),
      topCounterAriaLabel: t(labelPendingStatusServices)
    },
    unknown: {
      label: t(labelUnknown),
      onClick: changeFilterAndNavigate({
        criterias: unhandledUnknownServicesCriterias,
        link: unhandledUnknownServicesLink
      }),
      severityCode: SeverityCode.Low,
      shortCount: data.unknown.unhandled,
      to: unhandledUnknownServicesLink,
      tooltip: t(labelUnhandledOutOfTotal, {
        status: t(labelUnknown),
        total: data.unknown.total,
        unhandled: data.unknown.unhandled
      }),
      topCounterAriaLabel: t(labelUnknownStatusServices)
    },
    warning: {
      label: t(labelWarning),
      onClick: changeFilterAndNavigate({
        criterias: unhandledWarningServicesCriterias,
        link: unhandledWarningServicesLink
      }),
      severityCode: SeverityCode.Medium,
      shortCount: data.warning.unhandled,
      to: unhandledWarningServicesLink,
      tooltip: t(labelUnhandledOutOfTotal, {
        status: t(labelWarning),
        total: data.warning.total,
        unhandled: data.warning.unhandled
      }),
      topCounterAriaLabel: t(labelWarningStatusServices)
    }
  };

  return {
    allLink: servicesLink,
    allOnClick: config.all.onClick,
    buttonLabel: t(labelServices),
    counters: ['critical', 'warning', 'unknown', 'ok', 'pending'].map(
      (statusName) => {
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
      }
    )
  };
};

export default getServicePropsAdapter;
