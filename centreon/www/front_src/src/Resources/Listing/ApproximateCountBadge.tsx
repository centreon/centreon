import { Chip, CircularProgress, Tooltip } from '@mui/material';

import { atom, useAtom } from 'jotai';
import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  labelApproximateCount,
  labelApproximateCountTooltip,
  labelExactCountLoaded,
  labelLoadingExactCount
} from '../translatedLabels';

export const exactCountAtom = atom<number | null>(null);
export const exactCountLoadingAtom = atom<boolean>(false);

interface ApproximateCountBadgeProps {
  onRequestExactCount: () => void;
}

const ApproximateCountBadge = ({
  onRequestExactCount
}: ApproximateCountBadgeProps): JSX.Element => {
  const { t } = useTranslation();
  const [exactCount] = useAtom(exactCountAtom);
  const [isLoading] = useAtom(exactCountLoadingAtom);

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
        label={t(labelExactCountLoaded, {
          count: exactCount.toLocaleString()
        })}
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
