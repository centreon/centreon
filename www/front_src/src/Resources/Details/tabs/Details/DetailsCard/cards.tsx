import * as React from 'react';

import { pick } from 'ramda';

import ChecksIcon from '../../../../ChecksIcon';
import {
  labelCurrentStateDuration,
  labelMonitoringServer,
  labelTimezone,
  labelLastStateChange,
  labelLastCheck,
  labelNextCheck,
  labelCheckDuration,
  labelLatency,
  labelLastNotification,
  labelCurrentNotificationNumber,
  labelFqdn,
  labelAlias,
  labelGroups,
  labelCalculationType,
  labelCheck,
  labelPercentStateChange,
} from '../../../../translatedLabels';
import { ResourceDetails } from '../../../models';

import DetailsLine from './DetailsLine';
import PercentStateChangeCard from './PercentStateChangeCard';
import Groups from './Groups';

interface DetailCardLine {
  active?: boolean;
  field?: string | number | boolean | Array<unknown>;
  line: JSX.Element;
  title: string;
  xs?: 6 | 12;
}

interface DetailCardLineProps {
  details: ResourceDetails;
  t: (label: string) => string;
  toDateTime: (date: string | Date) => string;
}

const getDetailCardLines = ({
  details,
  toDateTime,
}: DetailCardLineProps): Array<DetailCardLine> => {
  const checksDisabled =
    details.active_checks === false && details.passive_checks === false;
  const activeChecksDisabled = details.active_checks === false;

  const displayChecksIcon = checksDisabled || activeChecksDisabled;

  return [
    {
      field: details.fqdn,
      line: <DetailsLine line={details.fqdn} />,
      title: labelFqdn,
      xs: 12,
    },
    {
      field: details.alias,
      line: <DetailsLine line={details.alias} />,
      title: labelAlias,
    },
    {
      field: details.monitoring_server_name,
      line: <DetailsLine line={details.monitoring_server_name} />,
      title: labelMonitoringServer,
    },
    {
      field: details.timezone,
      line: <DetailsLine line={details.timezone} />,
      title: labelTimezone,
    },
    {
      field: details.duration,
      line: <DetailsLine line={`${details.duration} - ${details.tries}`} />,
      title: labelCurrentStateDuration,
    },
    {
      field: details.last_status_change,
      line: <DetailsLine line={toDateTime(details.last_status_change)} />,
      title: labelLastStateChange,
    },
    {
      field: details.last_check,
      line: <DetailsLine line={toDateTime(details.last_check)} />,
      title: labelLastCheck,
    },
    {
      field: displayChecksIcon ? true : undefined,
      line: (
        <ChecksIcon {...pick(['active_checks', 'passive_checks'], details)} />
      ),

      title: labelCheck,
    },
    {
      field: details.next_check,
      line: <DetailsLine line={toDateTime(details.next_check)} />,
      title: labelNextCheck,
    },
    {
      field: details.execution_time,
      line: <DetailsLine line={`${details.execution_time} s`} />,
      title: labelCheckDuration,
    },
    {
      field: details.latency,
      line: <DetailsLine line={`${details.latency} s`} />,
      title: labelLatency,
    },
    {
      field: details.percent_state_change,
      line: <PercentStateChangeCard details={details} />,
      title: labelPercentStateChange,
    },
    {
      field: details.last_notification,
      line: <DetailsLine line={toDateTime(details.last_notification)} />,
      title: labelLastNotification,
    },
    {
      field: details.notification_number,
      line: <DetailsLine line={details.notification_number.toString()} />,
      title: labelCurrentNotificationNumber,
    },
    {
      field: details.calculation_type,
      line: <DetailsLine line={details.calculation_type} />,
      title: labelCalculationType,
    },
    {
      field: details.groups,
      line: <Groups details={details} />,
      title: labelGroups,
      xs: 12,
    },
  ];
};

export default getDetailCardLines;
