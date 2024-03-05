import { StatusGridProps } from '../StatusGridStandard/models';

import Skeleton from './Skeleton';
import { useStatusGridCondensedStyles } from './StatusGridCondensed.styles';
import { useStatusGridCondensed } from './useStatusGridCondensed';

const StatusGridCondensed = ({
  globalRefreshInterval,
  panelData,
  panelOptions,
  refreshCount
}: Omit<StatusGridProps, 'store'>): JSX.Element => {
  const { classes } = useStatusGridCondensedStyles();
  const { data, isLoading } = useStatusGridCondensed({
    globalRefreshInterval,
    panelData,
    panelOptions,
    refreshCount
  });

  return <Skeleton statuses={panelOptions.statuses} />;
};

export default StatusGridCondensed;
