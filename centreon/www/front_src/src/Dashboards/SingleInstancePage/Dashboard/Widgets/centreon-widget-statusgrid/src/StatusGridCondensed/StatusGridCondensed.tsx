import { equals, last, pipe, pluck, reject } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { formatMetricValue, usePluralizedTranslation } from '@centreon/ui';

import { StatusGridProps } from '../StatusGridStandard/models';

import Skeleton from './Skeleton';
import StatusCard from './StatusCard';
import { useStatusGridCondensedStyles } from './StatusGridCondensed.styles';
import { labelBusinessActivity } from './translatedLabels';
import { useStatusGridCondensed } from './useStatusGridCondensed';

const StatusGridCondensed = ({
  globalRefreshInterval,
  panelData,
  panelOptions,
  refreshCount,
  dashboardId,
  playlistHash,
  id,
  widgetPrefixQuery
}: Omit<StatusGridProps, 'store' | 'queryClient'>): JSX.Element => {
  const { classes } = useStatusGridCondensedStyles();
  const { t } = useTranslation();
  const { pluralizedT } = usePluralizedTranslation();

  const lastSelectedResourceType = pipe(
    pluck('resourceType'),
    reject((type) => equals(type, '')),
    last
  )(panelData?.resources);

  const isBVResourceType = equals(lastSelectedResourceType, 'business-view');
  const isBAResourceType = equals(
    lastSelectedResourceType,
    'business-activity'
  );

  const getResourceTypeLabel = (): string => {
    if (isBVResourceType) {
      return t(labelBusinessActivity);
    }
    if (isBAResourceType) {
      return 'KPI';
    }

    return panelOptions.resourceType;
  };

  const { statusesToDisplay, hasData, isLoading, total } =
    useStatusGridCondensed({
      dashboardId,
      globalRefreshInterval,
      id,
      isBAResourceType,
      isBVResourceType,
      lastSelectedResourceType,
      panelData,
      panelOptions,
      playlistHash,
      refreshCount,
      widgetPrefixQuery
    });

  if (isLoading && !hasData) {
    return <Skeleton statuses={panelOptions.statuses} />;
  }

  return (
    <div className={classes.container}>
      <Typography fontWeight="bold">
        {formatMetricValue({ unit: '', value: total || 0 })}{' '}
        {pluralizedT({
          count: total || 0,
          label: getResourceTypeLabel()
        })}
      </Typography>
      <div className={classes.statuses}>
        {statusesToDisplay.map(({ count, label, severityCode }) => (
          <StatusCard
            count={count}
            isBAResourceType={isBAResourceType}
            isBVResourceType={isBVResourceType}
            key={label}
            label={label}
            resourceType={panelOptions.resourceType}
            resources={panelData.resources}
            severityCode={severityCode}
            total={total}
          />
        ))}
      </div>
    </div>
  );
};

export default StatusGridCondensed;
