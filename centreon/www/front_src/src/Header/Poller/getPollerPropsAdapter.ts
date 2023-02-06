import { isNil, isEmpty } from "ramda";

import {
    labelDatabaseUpdatesNotActive,
    labelPollerNotRunning,
    labelDatabaseNotActive,
    labelDatabaseUpdateAndActive,
    labelLatencyDetected,
    labelNoLatencyDetected,
    labelAllPollers,
    labelConfigurePollers,
} from "./translatedLabels";

import { SeverityCode } from "@centreon/ui";


const pollerIssueKeyToMessage = {
    database: labelDatabaseUpdatesNotActive,
    latency: labelLatencyDetected,
    stability: labelPollerNotRunning,
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

export const pollerConfigurationPageNumber = "60901";

export const getPollerPropsAdapter = ({ data, t, allowedPages, navigate, isExportButtonEnabled }) => {
    const { total } = data
    const issues = data?.issues ?? {}
    const formatedIssues = !isEmpty(issues)
        ? Object.entries(issues).map(([key, issue]) => ({
            text: t(pollerIssueKeyToMessage[key]),
            total: issue.total || ''
        }))
        : []


    const databaseSeverity = getIssueSeverity({ issues, key: 'database' });
    const latencySeverity = getIssueSeverity({ issues, key: 'latency' });

    const topIconProps = {
        database: {
            label: databaseSeverity === SeverityCode.Ok
                ? t(labelDatabaseUpdateAndActive)
                : t(labelDatabaseNotActive),
            severity: databaseSeverity
        },
        latency: {
            label: latencySeverity === SeverityCode.Ok
                ? t(labelNoLatencyDetected)
                : t(labelLatencyDetected),
            severity: latencySeverity
        }
    }

    return {
        subMenu: {
            issues: formatedIssues,
            pollerCount: total,
            allPollerLabel: t(labelAllPollers),
            pollerConfig: {
                isAllowed: allowedPages?.includes(
                    pollerConfigurationPageNumber
                ),
                testId: labelConfigurePollers,
                redirect: () => navigate(`/main.php?p=${pollerConfigurationPageNumber}`),
                label: t(labelConfigurePollers)
            },
            exportConfig: {
                isExportButtonEnabled,
            }
        },
        iconSeverities: topIconProps
    }
}