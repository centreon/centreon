import { isNil, isEmpty } from 'ramda';
import type { NavigateFunction } from 'react-router-dom';
import type { TFunction } from 'react-i18next';

import { SeverityCode } from '@centreon/ui';

import type { PollerIssuesResponse } from '../api/decoders';

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

const getIssueSeverity = ({ issues, key }): SeverityCode => {
  if (!isNil(issues[key]?.warning)) {
    return SeverityCode.Medium;
  }
  if (!isNil(issues[key]?.critical)) {
    return SeverityCode.High;
  }

  return SeverityCode.Ok;
};

export const pollerConfigurationPageNumber = '60901';

interface GetPollerPropsAdapterProps {
  allowedPages: Array<string> | undefined;
  data: PollerIssuesResponse;
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
  const { total } = data;
  const issues = data?.issues ?? {};
  const formatedIssues = !isEmpty(issues)
    ? Object.entries(issues).map(([key, issue]) => ({
        key,
        text: t(pollerIssueKeyToMessage[key]),
        total: issue.total || ''
      }))
    : [];

  const databaseSeverity = getIssueSeverity({ issues, key: 'database' });
  const latencySeverity = getIssueSeverity({ issues, key: 'latency' });

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
