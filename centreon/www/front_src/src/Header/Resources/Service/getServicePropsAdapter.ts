import numeral from 'numeral';

import { SeverityCode } from '@centreon/ui';
import type { SelectEntry } from '@centreon/ui';

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
import type { Adapter } from '../useResourcesDatas';
import type { Criteria } from '../../../Resources/Filter/Criterias/models';
import type { SubMenuProps } from '../../sharedUI/ResourceSubMenu';
import type { CounterProps } from '../../sharedUI/ResourceCounters';
import type { ServiceStatusResponse } from '../../api/decoders';

import {
  criticalStatusServices,
  warningStatusServices,
  unknownStatusServices,
  okStatusServices
} from './translatedLabels';

type ChangeFilterAndNavigate = (
  link: string,
  criterias: Array<Criteria>
) => (e: React.MouseEvent<HTMLLinkElement>) => void;

type GetServicePropsAdapter = Adapter<
  ServiceStatusResponse,
  {
    counters: CounterProps['counters'];
    hasPending: boolean;
    items: SubMenuProps['items'];
  }
>;

const formatCount = (
  unhandled: number | string,
  total: number | string
): string =>
  `${numeral(unhandled).format('0a')}/${numeral(total).format('0a')}`;

const getServicePropsAdapter: GetServicePropsAdapter = ({
  useDeprecatedPages,
  applyFilter,
  navigate,
  t,
  data
}) => {
  const changeFilterAndNavigate: ChangeFilterAndNavigate =
    (link, criterias) => (e) => {
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
    all: {
      count: numeral(data.total).format('0a'),
      label: t('All'),
      onClick: changeFilterAndNavigate(servicesLink, servicesCriterias),
      shortCount: data.total,
      to: servicesLink
    },
    critical: {
      count: formatCount(data.critical.unhandled, data.critical.total),
      label: t('Critical'),
      onClick: changeFilterAndNavigate(
        unhandledCriticalServicesLink,
        unhandledCriticalServicesCriterias
      ),
      severityCode: SeverityCode.High,
      shortCount: data.critical.unhandled,
      to: unhandledCriticalServicesLink,
      topCounterAriaLabel: t(criticalStatusServices)
    },
    ok: {
      count: numeral(data.ok).format('0a'),
      label: t('Ok'),
      onClick: changeFilterAndNavigate(okServicesLink, okServicesCriterias),
      severityCode: SeverityCode.Ok,
      shortCount: data.ok,
      to: okServicesLink,
      topCounterAriaLabel: t(okStatusServices)
    },
    pending: {
      count: numeral(data.pending).format('0a'),
      label: t('Pending'),
      onClick: changeFilterAndNavigate(
        pendingServicesLink,
        pendingServicesCriterias
      ),
      severityCode: SeverityCode.Pending,
      shortCount: data.pending,
      to: pendingServicesLink
    },
    unknown: {
      count: formatCount(data.unknown.unhandled, data.unknown.total),
      label: t('Unknown'),
      onClick: changeFilterAndNavigate(
        unhandledUnknownServicesLink,
        unhandledUnknownServicesCriterias
      ),
      severityCode: SeverityCode.Low,
      shortCount: data.unknown.unhandled,
      to: unhandledUnknownServicesLink,
      topCounterAriaLabel: t(unknownStatusServices)
    },
    warning: {
      count: formatCount(data.warning.unhandled, data.warning.total),
      label: t('Warning'),
      onClick: changeFilterAndNavigate(
        unhandledWarningServicesLink,
        unhandledWarningServicesCriterias
      ),
      severityCode: SeverityCode.Medium,
      shortCount: data.warning.unhandled,
      to: unhandledWarningServicesLink,
      topCounterAriaLabel: t(warningStatusServices)
    }
  };

  return {
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
    hasPending: config.pending.count > 0,
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
