import {
  getServiceResourcesUrl,
  criticalCriterias,
  warningCriterias,
  unknownCriterias,
  okCriterias,
  pendingCriterias,
  unhandledStateCriterias,
  serviceCriteria
} from '../getResourcesUrl';
import getDefaultCriterias from '../../../Resources/Filter/Criterias/default';
import numeral from 'numeral';
import { SeverityCode } from '@centreon/ui';
import type { Adapter } from '../useResourcesDatas'
import type { Criteria } from '../../../Resources/Filter/Criterias/models';
import type { SelectEntry } from '@centreon/ui';

import type { SubMenuProps } from '../../sharedUI/ResourceSubMenu'
import type { CounterProps } from '../../sharedUI/ResourceCounters'
import type { serviceStatusDecoder } from '../../api/decoders'

type ChangeFilterAndNavigate = (
  link: string,
  criterias: Array<Criteria>,
) => (e: React.MouseEvent<HTMLLinkElement>) => void

type GetCounterPropsAdapter = Adapter<
  {}, // TODO: type input
  {
    counters: CounterProps['counters'];
    items: SubMenuProps['items'];
    hasPending: boolean;
  }>

const formatCount = (unhandled: number, total: number): string =>
  `${numeral(unhandled).format('0a')}/${numeral(total).format('0a')}`

const getCounterPropsAdapter: GetCounterPropsAdapter = ({ useDeprecatedPages, applyFilter, navigate, t, data }) => {
  const changeFilterAndNavigate: ChangeFilterAndNavigate = (link, criterias) => (e) => {
    e.preventDefault();
    if (!useDeprecatedPages) {
      applyFilter({ criterias, id: '', name: 'New Filter' });
    }
    navigate(link);
  };

  const unhandledCriticalServicesCriterias = getDefaultCriterias({
    resourceTypes: serviceCriteria.value,
    states: unhandledStateCriterias.value,
    statuses: criticalCriterias.value as Array<SelectEntry>
  });

  const unhandledCriticalServicesLink = useDeprecatedPages
    ? '/main.php?p=20201&o=svc_unhandled&statusFilter=critical&search='
    : getServiceResourcesUrl({
      stateCriterias: unhandledStateCriterias,
      statusCriterias: criticalCriterias
    });

  const unhandledWarningServicesCriterias = getDefaultCriterias({
    resourceTypes: serviceCriteria.value,
    states: unhandledStateCriterias.value,
    statuses: warningCriterias.value as Array<SelectEntry>
  });

  const unhandledWarningServicesLink = useDeprecatedPages
    ? '/main.php?p=20201&o=svc_unhandled&statusFilter=warning&search='
    : getServiceResourcesUrl({
      stateCriterias: unhandledStateCriterias,
      statusCriterias: warningCriterias
    });

  const unhandledUnknownServicesCriterias = getDefaultCriterias({
    resourceTypes: serviceCriteria.value,
    states: unhandledStateCriterias.value,
    statuses: unknownCriterias.value as Array<SelectEntry>
  });

  const unhandledUnknownServicesLink = useDeprecatedPages
    ? '/main.php?p=20201&o=svc_unhandled&statusFilter=unknown&search='
    : getServiceResourcesUrl({
      stateCriterias: unhandledStateCriterias,
      statusCriterias: unknownCriterias
    });

  const okServicesCriterias = getDefaultCriterias({
    resourceTypes: serviceCriteria.value,
    statuses: okCriterias.value as Array<SelectEntry>
  });

  const okServicesLink = useDeprecatedPages
    ? '/main.php?p=20201&o=svc&statusFilter=ok&search='
    : getServiceResourcesUrl({ statusCriterias: okCriterias });

  const servicesCriterias = getDefaultCriterias({
    resourceTypes: serviceCriteria.value
  });

  const servicesLink = useDeprecatedPages
    ? '/main.php?p=20201&o=svc&statusFilter=&search='
    : getServiceResourcesUrl();

  const pendingServicesCriterias = getDefaultCriterias({
    resourceTypes: serviceCriteria.value,
    statuses: pendingCriterias.value as Array<SelectEntry>
  });

  const pendingServicesLink = useDeprecatedPages
    ? '/main.php?p=20201&o=svc&statusFilter=pending&search='
    : getServiceResourcesUrl({
      statusCriterias: pendingCriterias
    });

  const config = {
    critical: {
      onClick: changeFilterAndNavigate(unhandledCriticalServicesLink, unhandledCriticalServicesCriterias),
      to: unhandledCriticalServicesLink,
      label: t('Critical'),
      severityCode: SeverityCode.High,
      shortCount: data.critical.unhandled,
      count: formatCount(data.critical.unhandled, data.critical.total),
    },
    warning: {
      onClick: changeFilterAndNavigate(unhandledWarningServicesLink, unhandledWarningServicesCriterias),
      to: unhandledWarningServicesLink,
      label: t('Warning'),
      severityCode: SeverityCode.Medium,
      shortCount: data.warning.unhandled,
      count: formatCount(data.warning.unhandled, data.warning.total),
    },
    unknown: {
      onClick: changeFilterAndNavigate(unhandledUnknownServicesLink, unhandledUnknownServicesCriterias),
      to: unhandledUnknownServicesLink,
      label: t('Unknown'),
      severityCode: SeverityCode.Low,
      shortCount: data.unknown.unhandled,
      count: formatCount(data.unknown.unhandled, data.unknown.total),
    },
    ok: {
      onClick: changeFilterAndNavigate(okServicesLink, okServicesCriterias),
      to: okServicesLink,
      label: t('Ok'),
      severityCode: SeverityCode.Ok,
      shortCount: data.ok,
      count: numeral(data.ok).format('0a'),
    },
    all: {
      onClick: changeFilterAndNavigate(servicesLink, servicesCriterias),
      to: servicesLink,
      label: t('All'),
      shortCount: data.total,
      count: numeral(data.total).format('0a'),
    },
    pending: {
      onClick: changeFilterAndNavigate(pendingServicesLink, pendingServicesCriterias),
      to: pendingServicesLink,
      serverityCode: SeverityCode.Pending,
      label: t('Pending'),
      shortCount: data.pending,
      count: numeral(data.pending).format('0a'),
    }
  }

  return {
    counters: ["critical", "warning", "unknown", "ok"].map(
      (statusName) => {
        const { to, shortCount, label, onClick, severityCode } = config[statusName];

        return {
          to,
          onClick,
          ariaLabel: label,
          count: shortCount,
          severityCode,
        };
      }
    ),
    items: ["all", "critical", "warning", "unknown", "ok", "pending"].map(
      (status) => {
        const { onClick, severityCode, count, label, to } = config[status];

        return {
          onClick,
          severityCode,
          submenuCount: count,
          submenuTitle: label,
          to,
        };
      }
    ),
    hasPending: config.pending.count > 0
  }
}

export default getCounterPropsAdapter