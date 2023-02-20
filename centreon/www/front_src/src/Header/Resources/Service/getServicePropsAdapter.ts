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
import type { Adapter } from '../useResourceCounters';
import type { Criteria } from '../../../Resources/Filter/Criterias/models';
import type { SubMenuProps } from '../../sharedUI/ResourceSubMenu';
import type { CounterProps } from '../../sharedUI/ResourceCounters';
import type { ServiceStatusResponse } from '../../api/decoders';

import {
  labelCriticalStatusServices,
  labelWarningStatusServices,
  labelUnknownStatusServices,
  labelOkStatusServices,
  labelAll,
  labelCritical,
  labelWarning,
  labelPending,
  labelUnknown,
  labelOk
} from './translatedLabels';

type ChangeFilterAndNavigate = (params: {
  criterias: Array<Criteria>;
  link: string;
}) => (e: React.MouseEvent<HTMLLinkElement>) => void;

export interface ServicesPropsAdapterOutput {
  counters: CounterProps['counters'];
  hasPending: boolean;
  items: SubMenuProps['items'];
}

type GetServicePropsAdapter = Adapter<
  ServiceStatusResponse,
  ServicesPropsAdapterOutput
>;

const formatCount = (number: number | string): string =>
  numeral(number).format('0a');

const formatUnhandledOverTotal = (
  unhandled: number | string,
  total: number | string
): string => `${formatCount(unhandled)}/${formatCount(total)}`;

const getServicePropsAdapter: GetServicePropsAdapter = ({
  useDeprecatedPages,
  applyFilter,
  navigate,
  t,
  data
}) => {
  const changeFilterAndNavigate: ChangeFilterAndNavigate =
    ({ link, criterias }) =>
    (e) => {
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
      severityCode: SeverityCode.Ok,
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
