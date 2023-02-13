import { isNil, isEmpty } from 'ramda';
import type { NavigateFunction } from 'react-router-dom';
import type { TFunction } from 'react-i18next';

import { SeverityCode } from '@centreon/ui';

import type { PollersIssuesList } from '../api/models';

import {
  labelDatabaseUpdatesNotActive,
  labelPollerNotRunning,
  labelDatabaseNotActive,
  labelDatabaseUpdateAndActive,
  labelLatencyDetected,
  labelNoLatencyDetected,
  labelAllPollers,
  labelConfigurePollers
} from './translatedLabels';
import type { PollerStatusIconProps } from './PollerStatusIcon';
import type { PollerSubMenuProps } from './PollerSubMenu/PollerSubMenu';

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
  key: keyof PollersIssuesList['issues'];
}): SeverityCode => {
  if (!!issues[key]?.warning?.total && !isNil(issues[key]?.warning?.total)) {
    return SeverityCode.Medium;
  }

  if (issues[key]?.critical?.total && !isNil(issues[key]?.critical?.total)) {
    return SeverityCode.High;
  }

  return SeverityCode.Ok;
};

export const pollerConfigurationPageNumber = '60901';

interface GetPollerPropsAdapterProps {
  allowedPages: Array<string | Array<string>> | undefined;
  data: PollersIssuesList;
  isExportButtonEnabled: boolean;
  navigate: NavigateFunction;
  t: TFunction<'translation', undefined>;
}

export interface GetPollerPropsAdapterResult {
  iconSeverities: PollerStatusIconProps['iconSeverities'];
  subMenu: Omit<PollerSubMenuProps, 'closeSubMenu'>;
}

export const getPollerPropsAdapter = ({
  data,
  t,
  allowedPages,
  navigate,
  isExportButtonEnabled
}: GetPollerPropsAdapterProps): GetPollerPropsAdapterResult => {
  const { total, issues } = data;
  const formatedIssues = !isEmpty(issues)
    ? Object.entries(issues)
        .filter(([_, issue]) => !!issue && issue.total > 0)
        .map(([key, issue]) => ({
          key,
          text: t(pollerIssueKeyToMessage[key]),
          total: issue?.total.toString() || ''
        }))
    : [];

  const databaseSeverity = getIssueSeverityCode({ issues, key: 'database' });
  const latencySeverity = getIssueSeverityCode({ issues, key: 'latency' });

  const topIconProps = {
    database: {
      label:
        databaseSeverity === SeverityCode.Ok
          ? t(labelDatabaseUpdateAndActive)
          : t(labelDatabaseNotActive),
      severity: databaseSeverity
    },
    latency: {
      label:
        latencySeverity === SeverityCode.Ok
          ? t(labelNoLatencyDetected)
          : t(labelLatencyDetected),
      severity: latencySeverity
    }
  };

  const result = {
    iconSeverities: topIconProps,
    subMenu: {
      allPollerLabel: t(labelAllPollers),
      exportConfig: {
        isExportButtonEnabled
      },
      issues: formatedIssues,
      pollerConfig: {
        isAllowed: allowedPages?.includes(pollerConfigurationPageNumber),
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
