import pluralize from 'pluralize';
import { always, cond, equals, reject } from 'ramda';

import { capitalize } from '@mui/material';

import { SeverityCode } from '@centreon/ui';

import {
  labelCritical,
  labelDown,
  labelOk,
  labelPending,
  labelUnknown,
  labelUnreachable,
  labelUp,
  labelWarning
} from './translatedLabels';

export const getResourceTypeName = (resourceType?: string | null): string => {
  if (!resourceType) {
    return '';
  }

  const [firstPart, secondPart] = resourceType.split('-');

  if (!secondPart) {
    return pluralize(firstPart);
  }

  return pluralize(`${capitalize(firstPart)} ${secondPart}`);
};

export const formatResourceTypeToCriterias = (resourceType: string): string => {
  const [firstPart, secondPart] = resourceType.split('-');

  if (!secondPart) {
    return pluralize(firstPart);
  }

  return `${firstPart}_${pluralize(secondPart)}`;
};

interface GetStatusesCountFromResourcesProps {
  resourceType: string;
  resources: Array<{ id: number; status: number }>;
  statuses: Array<string>;
}

const getSeverityCodeFromMonitoringStatus = ({
  resourceType,
  status
}: {
  resourceType: string;
  status: number;
}): SeverityCode => {
  if (equals(resourceType, 'service')) {
    return cond([
      [equals(0), always(SeverityCode.OK)],
      [equals(1), always(SeverityCode.Medium)],
      [equals(2), always(SeverityCode.High)],
      [equals(3), always(SeverityCode.None)],
      [equals(4), always(SeverityCode.Pending)]
    ])(status);
  }

  return cond([
    [equals(0), always(SeverityCode.OK)],
    [equals(1), always(SeverityCode.High)],
    [equals(2), always(SeverityCode.None)],
    [equals(4), always(SeverityCode.Pending)]
  ])(status);
};

const getSeverityCodeName = ({ resourceType, severityCode }): string => {
  const isService = equals(resourceType, 'service');

  return cond([
    [equals(SeverityCode.High), always(isService ? labelCritical : labelDown)],
    [equals(SeverityCode.Medium), always(labelWarning)],
    [equals(SeverityCode.OK), always(isService ? labelOk : labelUp)],
    [
      equals(SeverityCode.None),
      always(isService ? labelUnknown : labelUnreachable)
    ],
    [equals(SeverityCode.Pending), always(labelPending)]
  ])(severityCode);
};

export const getStatusesCountFromResources = ({
  resources,
  statuses,
  resourceType
}: GetStatusesCountFromResourcesProps): Array<{
  count: number;
  label: string;
  severityCode: number;
}> => {
  const filteredStatuses = equals(resourceType, 'host')
    ? reject((status) => equals(SeverityCode.Medium, Number(status)), statuses)
    : statuses;
  const sortedStatuses = filteredStatuses.sort();

  return sortedStatuses.map((status) => {
    const formattedStatus = Number(status);

    return {
      count: resources.filter(({ status: resourceStatus }) =>
        equals(
          formattedStatus,
          getSeverityCodeFromMonitoringStatus({
            resourceType,
            status: resourceStatus
          })
        )
      ).length,
      label: getSeverityCodeName({
        resourceType,
        severityCode: formattedStatus
      }),
      severityCode: formattedStatus
    };
  });
};
