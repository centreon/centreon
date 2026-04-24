import { Chip, CircularProgress, Tooltip } from '@mui/material';

import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  labelApproximateCount,
  labelApproximateCountTooltip,
  labelExactCountLoaded,
  labelLoadingExactCount
} from './translatedLabels';

interface ApproximateCountBadgeProps {
  exactCount: number | null;
  isLoading: boolean;
  onRequestExactCount: () => void;
}

const ApproximateCountBadge = ({
  exactCount,
  isLoading,
  onRequestExactCount
}: ApproximateCountBadgeProps): JSX.Element => {
  const { t } = useTranslation();

  if (isLoading) {
    return (
      <Chip
        icon={<CircularProgress size={14} />}
        label={t(labelLoadingExactCount)}
        size="small"
        variant="outlined"
      />
    );
  }

  if (!isNil(exactCount)) {
    return (
      <Chip
        label={t(labelExactCountLoaded, { count: exactCount.toLocaleString() })}
        size="small"
        variant="outlined"
      />
    );
  }

  return (
    <Tooltip title={t(labelApproximateCountTooltip)}>
      <Chip
        clickable
        color="warning"
        label={t(labelApproximateCount)}
        onClick={onRequestExactCount}
        size="small"
        variant="outlined"
      />
    </Tooltip>
  );
};

export { ApproximateCountBadge };
