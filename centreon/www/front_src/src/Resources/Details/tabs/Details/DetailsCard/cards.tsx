import { equals, includes, isEmpty, isNil } from 'ramda';

import { SeverityCode } from '@centreon/ui';

import ChecksIcon from '../../../../ChecksIcon';
import { CriteriaNames } from '../../../../Filter/Criterias/models';
import { ResourceType } from '../../../../models';
import {
  labelAcknowledgement,
  labelAlias,
  labelCalculationType,
  labelCategories,
  labelCheck,
  labelCheckDuration,
  labelCommand,
  labelCurrentNotificationNumber,
  labelCurrentStatusDuration,
  labelDowntimeDuration,
  labelFqdn,
  labelGroups,
  labelLastCheck,
  labelLastCheckWithOkStatus,
  labelLastNotification,
  labelLastStatusChange,
  labelLatency,
  labelMonitoringServer,
  labelNextCheck,
  labelPerformanceData,
  labelSeverity,
  labelStatusChangePercentage,
  labelStatusInformation,
  labelTimezone
} from '../../../../translatedLabels';
import type { ResourceDetails } from '../../../models';
import ExpandableCard from '../ExpandableCard';
import type { ChangeExpandedCardsProps } from '../SortableCards/models';

import AcknowledgementCard from './AcknowledegmentCard';
import CommandLineCard from './CommandLineCard';
import DetailsLine from './DetailsLine';
import DowntimesCard from './DowntimesCard';
import GroupChips from './GroupChips';
import PercentStateChangeCard from './PercentStateChangeCard';
import SeverityCard from './SeverityCard';

export interface DetailCardLine {
  active?: boolean;
  isCustomCard?: boolean;
  line: JSX.Element;
  shouldBeDisplayed: boolean;
  title: string;
  xs?: 6 | 12;
}

interface DetailCardLineProps {
  changeExpandedCards: (props: ChangeExpandedCardsProps) => void;
  details: ResourceDetails;
  expandedCards: Array<string>;
  t: (label: string) => string;
  toDateTime: (date: string | Date) => string;
}

const getDetailCardLines = ({
  details,
  toDateTime,
  t,
  expandedCards,
  changeExpandedCards
}: DetailCardLineProps): Array<DetailCardLine> => {
  const checksDisabled =
    details.has_active_checks_enabled === false &&
    details.has_passive_checks_enabled === false;
  const activeChecksDisabled = details.has_active_checks_enabled === false;

  const displayChecksIcon = checksDisabled || activeChecksDisabled;

  return [
    {
      isCustomCard: true,
      line: (
        <ExpandableCard
          changeExpandedCards={changeExpandedCards}
          content={details.information}
          expandedCard={includes(t(labelStatusInformation), expandedCards)}
          severityCode={details.status.severity_code}
          title={t(labelStatusInformation)}
        />
      ),
      shouldBeDisplayed: !isNil(details.information),
      title: labelStatusInformation,
      xs: 12
    },
    {
      isCustomCard: true,
      line: <DowntimesCard details={details} />,
      shouldBeDisplayed: !isEmpty(details.downtimes),
      title: labelDowntimeDuration,
      xs: 12
    },
    {
      isCustomCard: true,
      line: <AcknowledgementCard details={details} />,
      shouldBeDisplayed: !isNil(details.acknowledgement),
      title: labelAcknowledgement,
      xs: 12
    },
    {
      isCustomCard: true,
      line: <SeverityCard details={details} />,
      shouldBeDisplayed: !isNil(details.severity),
      title: labelSeverity,
      xs: 12
    },
    {
      line: <DetailsLine line={details.fqdn} />,
      shouldBeDisplayed: !isNil(details.fqdn),
      title: labelFqdn,
      xs: 12
    },
    {
      line: <DetailsLine line={details.alias} />,
      shouldBeDisplayed: !isNil(details.alias),
      title: labelAlias
    },
    {
      line: <DetailsLine line={details.monitoring_server_name} />,
      shouldBeDisplayed: !isNil(details.monitoring_server_name),
      title: labelMonitoringServer
    },
    {
      line: <DetailsLine line={details.timezone} />,
      shouldBeDisplayed: !isNil(details.timezone) && !isEmpty(details.timezone),
      title: labelTimezone
    },
    {
      line: <DetailsLine line={`${details.duration} - ${details.tries}`} />,
      shouldBeDisplayed: !isNil(details.duration),
      title: labelCurrentStatusDuration
    },
    {
      line: <DetailsLine line={toDateTime(details.last_status_change)} />,
      shouldBeDisplayed: Boolean(details.last_status_change),
      title: labelLastStatusChange
    },
    {
      line: <DetailsLine line={toDateTime(details.last_check)} />,
      shouldBeDisplayed: Boolean(details.last_check),
      title: labelLastCheck
    },
    {
      line: <DetailsLine line={toDateTime(details.last_time_with_no_issue)} />,
      shouldBeDisplayed:
        Boolean(details.last_time_with_no_issue) &&
        !equals(details.status.severity_code, SeverityCode.OK),
      title: labelLastCheckWithOkStatus
    },
    {
      line: (
        <ChecksIcon
          has_active_checks_enabled={details?.has_active_checks_enabled}
          has_passive_checks_enabled={details?.has_passive_checks_enabled}
        />
      ),
      shouldBeDisplayed: displayChecksIcon,
      title: labelCheck
    },
    {
      line: <DetailsLine line={toDateTime(details.next_check)} />,
      shouldBeDisplayed: Boolean(details.next_check),
      title: labelNextCheck
    },
    {
      line: <DetailsLine line={`${details.execution_time} s`} />,
      shouldBeDisplayed: !isNil(details.execution_time),
      title: labelCheckDuration
    },
    {
      line: <DetailsLine line={`${details.latency} s`} />,
      shouldBeDisplayed: !isNil(details.latency),
      title: labelLatency
    },
    {
      line: <PercentStateChangeCard details={details} />,
      shouldBeDisplayed: !isNil(details.percent_state_change),
      title: labelStatusChangePercentage
    },
    {
      line: <DetailsLine line={toDateTime(details.last_notification)} />,
      shouldBeDisplayed: Boolean(details.last_notification),
      title: labelLastNotification
    },
    {
      line: <DetailsLine line={details.notification_number.toString()} />,
      shouldBeDisplayed: !isNil(details.notification_number),
      title: labelCurrentNotificationNumber
    },
    {
      line: <DetailsLine line={details.calculation_type} />,
      shouldBeDisplayed: !isNil(details.calculation_type),
      title: labelCalculationType
    },
    {
      line: <DetailsLine line={details.parent?.uuid} />,
      shouldBeDisplayed: !isNil(details.calculation_type),
      title: labelCalculationType
    },
    {
      isCustomCard: true,
      line: (
        <GroupChips
          getType={(): CriteriaNames =>
            equals(details?.type, ResourceType.host)
              ? CriteriaNames.hostGroups
              : CriteriaNames.serviceGroups
          }
          groups={details?.groups}
          title={labelGroups}
        />
      ),
      shouldBeDisplayed: !isEmpty(details.groups),
      title: labelGroups,
      xs: 12
    },
    {
      isCustomCard: true,
      line: (
        <GroupChips
          getType={(): CriteriaNames =>
            equals(details?.type, ResourceType.host)
              ? CriteriaNames.hostCategories
              : CriteriaNames.serviceCategories
          }
          groups={details?.categories}
          title={labelCategories}
        />
      ),
      shouldBeDisplayed: !isEmpty(details.categories),
      title: labelCategories,
      xs: 12
    },
    {
      isCustomCard: true,
      line: (
        <ExpandableCard
          changeExpandedCards={changeExpandedCards}
          content={details.performance_data || ''}
          expandedCard={includes(t(labelPerformanceData), expandedCards)}
          title={t(labelPerformanceData)}
        />
      ),
      shouldBeDisplayed: !isEmpty(details.performance_data),
      title: labelPerformanceData,
      xs: 12
    },
    {
      isCustomCard: true,
      line: <CommandLineCard details={details} />,
      shouldBeDisplayed: !isNil(details.command_line),
      title: labelCommand,
      xs: 12
    }
  ];
};

export default getDetailCardLines;
