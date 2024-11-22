import { is, isEmpty, isNil } from 'ramda';
import type { TFunction } from 'react-i18next';
import type { NavigateFunction } from 'react-router-dom';

import { SeverityCode } from '@centreon/ui';

import type { NonNullIssues, PollersIssuesList } from '../api/models';

import type { PollerStatusIconProps } from './PollerStatusIcon';
import type { PollerSubMenuProps } from './PollerSubMenu/PollerSubMenu';
import {
  labelAllPollers,
  labelConfigurePollers,
  labelDatabaseNotActive,
  labelDatabaseUpdateAndActive,
  labelDatabaseUpdatesNotActive,
  labelLatencyDetected,
  labelNoLatencyDetected,
  labelPollerNotRunning,
  labelPollers
} from './translatedLabels';

const pollerIssueKeyToMessage = {
  database: labelDatabaseUpdatesNotActive,
  latency: labelLatencyDetected,
  stability: labelPollerNotRunning
};

const getIssueSeverityCode = ({
  issues,
  key
}: {
  issues: PollersIssuesList['issues'];
  key: keyof NonNullIssues;
}): SeverityCode => {
  const issueExists = !isEmpty(issues) && !isNil(issues[key]);

  if (issueExists && issues[key].warning?.total > 0) {
    return SeverityCode.Medium;
  }

  if (issueExists && issues[key].critical?.total > 0) {
    return SeverityCode.High;
  }

  return SeverityCode.OK;
};

export const pollerConfigurationPageNumber = '60901';

interface GetPollerPropsAdapterProps {
  data: PollersIssuesList;
  isExportButtonEnabled: boolean;
  navigate: NavigateFunction;
  t: TFunction<'translation', undefined>;
}

export interface GetPollerPropsAdapterResult {
  buttonLabel: string;
  iconSeverities: PollerStatusIconProps['iconSeverities'];
  subMenu: Omit<PollerSubMenuProps, 'closeSubMenu'>;
}

export const getPollerPropsAdapter = ({
  data,
  t,
  navigate,
  isExportButtonEnabled
}: GetPollerPropsAdapterProps): GetPollerPropsAdapterResult => {
  const { total, issues } = data;

  // api inconsistency return an empty array when there is no issues
  const formatedIssues = !is(Array, issues)
    ? Object.keys(issues)
        .filter((key) => !!issues[key] && issues[key]?.total > 0)
        .map((key) => ({
          key,
          text: t(pollerIssueKeyToMessage[key]),
          total: issues[key]?.total.toString() || ''
        }))
    : [];

  const databaseSeverity = getIssueSeverityCode({ issues, key: 'database' });
  const latencySeverity = getIssueSeverityCode({ issues, key: 'latency' });

  const topIconProps = {
    database: {
      label:
        databaseSeverity === SeverityCode.OK
          ? t(labelDatabaseUpdateAndActive)
          : t(labelDatabaseNotActive),
      severity: databaseSeverity
    },
    latency: {
      label:
        latencySeverity === SeverityCode.OK
          ? t(labelNoLatencyDetected)
          : t(labelLatencyDetected),
      severity: latencySeverity
    }
  };

  const result = {
    buttonLabel: t(labelPollers),
    iconSeverities: topIconProps,
    subMenu: {
      allPollerLabel: t(labelAllPollers),
      exportConfig: {
        isExportButtonEnabled
      },
      issues: formatedIssues,
      pollerConfig: {
        label: t(labelConfigurePollers),
        redirect: (): void =>
          navigate(`/main.php?p=${pollerConfigurationPageNumber}`),
        testId: labelConfigurePollers
      },
      pollerCount: total
    }
  };

  return result;
};
