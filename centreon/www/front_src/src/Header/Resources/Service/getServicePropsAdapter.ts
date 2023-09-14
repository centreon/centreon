import type { CounterProps, SelectEntry, SubMenuProps } from '@centreon/ui';
import { SeverityCode } from '@centreon/ui';

import getDefaultCriterias from '../../../Resources/Filter/Criterias/default';
import type { ServiceStatusResponse } from '../../api/decoders';
import {
  criticalCriterias,
  getResourcesUrl,
  okCriterias,
  pendingCriterias,
  unhandledStateCriterias,
  unknownCriterias,
  warningCriterias
} from '../getResourcesUrl';
import type { Adapter } from '../useResourceCounters';
import {
  formatCount,
  formatUnhandledOverTotal,
  getNavigationFunction
} from '../utils';

import {
  labelAll,
  labelCritical,
  labelCriticalStatusServices,
  labelOk,
  labelOkStatusServices,
  labelPending,
  labelServices,
  labelUnknown,
  labelUnknownStatusServices,
  labelWarning,
  labelWarningStatusServices
} from './translatedLabels';

export interface ServicesPropsAdapterOutput {
  buttonLabel: string;
  counters: CounterProps['counters'];
  hasPending: boolean;
  items: SubMenuProps['items'];
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
  data
}) => {
  const resourceTypeCriterias = {
    name: 'resource_types',
    value: []
  };
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
      count: formatCount(data.total),
      label: t(labelAll),
      onClick: changeFilterAndNavigate({
        criterias: servicesCriterias,
        link: servicesLink
      }),
      shortCount: data.total,
      to: servicesLink
    },
    critical: {
      count: formatUnhandledOverTotal(
        data.critical.unhandled,
        data.critical.total
      ),
      label: t(labelCritical),
      onClick: changeFilterAndNavigate({
        criterias: unhandledCriticalServicesCriterias,
        link: unhandledCriticalServicesLink
      }),
      severityCode: SeverityCode.High,
      shortCount: data.critical.unhandled,
      to: unhandledCriticalServicesLink,
      topCounterAriaLabel: t(labelCriticalStatusServices)
    },
    ok: {
      count: formatCount(data.ok),
      label: t(labelOk),
      onClick: changeFilterAndNavigate({
        criterias: okServicesCriterias,
        link: okServicesLink
      }),
      severityCode: SeverityCode.OK,
      shortCount: data.ok,
      to: okServicesLink,
      topCounterAriaLabel: t(labelOkStatusServices)
    },
    pending: {
      count: formatCount(data.pending),
      label: t(labelPending),
      onClick: changeFilterAndNavigate({
        criterias: pendingServicesCriterias,
        link: pendingServicesLink
      }),
      severityCode: SeverityCode.Pending,
      shortCount: data.pending,
      to: pendingServicesLink
    },
    unknown: {
      count: formatUnhandledOverTotal(
        data.unknown.unhandled,
        data.unknown.total
      ),
      label: t(labelUnknown),
      onClick: changeFilterAndNavigate({
        criterias: unhandledUnknownServicesCriterias,
        link: unhandledUnknownServicesLink
      }),
      severityCode: SeverityCode.Low,
      shortCount: data.unknown.unhandled,
      to: unhandledUnknownServicesLink,
      topCounterAriaLabel: t(labelUnknownStatusServices)
    },
    warning: {
      count: formatUnhandledOverTotal(
        data.warning.unhandled,
        data.warning.total
      ),
      label: t(labelWarning),
      onClick: changeFilterAndNavigate({
        criterias: unhandledWarningServicesCriterias,
        link: unhandledWarningServicesLink
      }),
      severityCode: SeverityCode.Medium,
      shortCount: data.warning.unhandled,
      to: unhandledWarningServicesLink,
      topCounterAriaLabel: t(labelWarningStatusServices)
    }
  };

  return {
    buttonLabel: t(labelServices),
    counters: ['critical', 'warning', 'unknown', 'ok'].map((statusName) => {
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
    items: ['critical', 'warning', 'unknown', 'ok', 'pending', 'all'].map(
      (status) => {
        const { onClick, severityCode, count, label, to } = config[status];

        return {
          onClick,
          severityCode,
          submenuCount: count,
          submenuTitle: label,
          to
        };
      }
    )
  };
};

export default getServicePropsAdapter;
