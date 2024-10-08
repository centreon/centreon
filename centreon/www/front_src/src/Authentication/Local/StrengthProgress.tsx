import { useMemo } from 'react';

import { findLast, gt, lt } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { LinearProgress, Typography, alpha } from '@mui/material';

import memoizeComponent from '../../Resources/memoizedComponent';

interface Threshold {
  color: string;
  label: string;
  value: number;
}

interface Props {
  isInverted?: boolean;
  max: number;
  thresholds: Array<Threshold>;
  value: number;
}
const useStyles = makeStyles<Pick<Threshold, 'color'>>()(
  (theme, { color }) => ({
    linear: {
      backgroundColor: color
    },
    linearBackground: {
      backgroundColor: alpha(color, 0.3),
      width: '100%'
    },
    progressContainer: {
      alignItems: 'center',
      columnGap: theme.spacing(1),
      display: 'flex',
      width: '100%'
    }
  })
);

const StrengthProgress = ({
  thresholds,
  max,
  value,
  isInverted = false
}: Props): JSX.Element => {
  const currentThreshold = useMemo(
    () =>
      findLast((threshold) => value >= threshold.value, thresholds) ||
      thresholds[0],
    [thresholds, value]
  );
  const { classes } = useStyles(currentThreshold);
  const { t } = useTranslation();

  const { label } = currentThreshold;

  const computeProgress = (): number =>
    gt(value, max) ? 100 : (value / max) * 100;

  const computeInvertedProgress = (): number =>
    lt(value, 1) ? 100 : ((max + 1 - value) / max) * 100;

  const progressValue = isInverted
    ? computeInvertedProgress()
    : computeProgress();

  return (
    <div className={classes.progressContainer}>
      <LinearProgress
        aria-label={t(label)}
        classes={{
          bar: classes.linear,
          root: classes.linearBackground
        }}
        value={progressValue}
        variant="determinate"
      />
      <Typography variant="caption">{t(label)}</Typography>
    </div>
  );
};

export default memoizeComponent<Props>({
  Component: StrengthProgress,
  memoProps: ['thresholds', 'max', 'value']
});
