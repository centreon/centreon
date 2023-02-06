import {
    getHostResourcesUrl,
    upCriterias,
    unreachableCriterias,
    downCriterias,
    pendingCriterias,
    unhandledStateCriterias,
    hostCriterias,
} from '../getResourcesUrl';
import getDefaultCriterias from '../../../Resources/Filter/Criterias/default';
import numeral from 'numeral';
import { SeverityCode } from '@centreon/ui';
import type { Adapter } from '../useResourcesDatas'
import type { Criteria } from '../../../Resources/Filter/Criterias/models';
import type { SelectEntry } from '@centreon/ui';

import type { SubMenuProps } from '../../sharedUI/ResourceSubMenu'
import type { CounterProps } from '../../sharedUI/ResourceCounters'
import type { HostStatusSchemaResponse } from './schemaValidator'

type ChangeFilterAndNavigate = (
    link: string,
    criterias: Array<Criteria>,
) => (e: React.MouseEvent<HTMLLinkElement>) => void

type GetCounterPropsAdapter = Adapter<
    HostStatusSchemaResponse,
    {
        counters: CounterProps['counters'];
        items: SubMenuProps['items'];
        hasPending: boolean;
    }
>

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
        down: {
            onClick: changeFilterAndNavigate(unhandledDownHostsLink, unhandledDownHostsCriterias),
            to: unhandledDownHostsLink,
            label: t('Down'),
            severityCode: SeverityCode.High,
            shortCount: data.down.unhandled,
            count: formatCount(data.down.unhandled, data.down.total),
        },
        unreachable: {
            onClick: changeFilterAndNavigate(unhandledUnreachableHostsLink, unhandledUnreachableHostsCriterias),
            to: unhandledUnreachableHostsLink,
            label: t('Unreachable'),
            severityCode: SeverityCode.Medium,
            shortCount: data.unreachable.unhandled,
            count: formatCount(data.unreachable.unhandled, data.unreachable.total),
        },
        up: {
            onClick: changeFilterAndNavigate(upHostsLink, upHostsCriterias),
            to: upHostsLink,
            label: t('Up'),
            severityCode: SeverityCode.Ok,
            shortCount: data.ok,
            count: numeral(data.ok).format('0a'),
        },
        all: {
            onClick: changeFilterAndNavigate(hostsLink, hostsCriterias),
            to: hostsLink,
            label: t('All'),
            serverityCode: null,
            shortCount: data.total,
            count: numeral(data.total).format('0a'),
        },
        pending: {
            onClick: changeFilterAndNavigate(pendingHostsLink, pendingHostsCriterias),
            to: pendingHostsLink,
            serverityCode: SeverityCode.Pending,
            label: t('Pending'),
            shortCount: data.pending,
            count: numeral(data.pending).format('0a'),
        }
    }

    return {
        counters: ["down", "unreachable", "up"].map(
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
        items: ["all", "down", "unreachable", "up", "pending"].map(
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