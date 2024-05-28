import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { formatMetricValue } from '@centreon/ui';

import { StatusGridProps } from '../StatusGridStandard/models';

import Skeleton from './Skeleton';
import { useStatusGridCondensedStyles } from './StatusGridCondensed.styles';
import { useStatusGridCondensed } from './useStatusGridCondensed';
import StatusCard from './StatusCard';

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

  const { statusesToDisplay, hasData, isLoading, total } =
    useStatusGridCondensed({
      dashboardId,
      globalRefreshInterval,
      id,
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
        {t(`${panelOptions.resourceType}s`)}
      </Typography>
      <div className={classes.statuses}>
        {statusesToDisplay.map(({ count, label, severityCode }) => (
          <StatusCard
            count={count}
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
