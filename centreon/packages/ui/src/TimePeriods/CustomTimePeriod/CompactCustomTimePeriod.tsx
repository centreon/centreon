import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import AccessTimeIcon from '@mui/icons-material/AccessTime';
import { Button, Typography } from '@mui/material';

import { dateTimeFormat, useLocaleDateTimeFormat } from '@centreon/ui';

import { customTimePeriodAtom } from '../timePeriodsAtoms';
import {
  labelCompactTimePeriod,
  labelFrom,
  labelTo
} from '../translatedLabels';

import useStyles from './CompactCustomTimePeriod.styles';

interface Props {
  disabled?: boolean;
  isCondensed?: boolean;
  onClick: (event) => void;
}

const CompactCustomTimePeriod = ({
  onClick,
  disabled = false,
  isCondensed = false
}: Props): JSX.Element => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();

  const { format } = useLocaleDateTimeFormat();

  const customTimePeriod = useAtomValue(customTimePeriodAtom);

  return (
    <Button
      aria-label={t(labelCompactTimePeriod) as string}
      className={classes.button}
      color="primary"
      data-testid={labelCompactTimePeriod}
      disabled={disabled}
      variant="outlined"
      onClick={onClick}
    >
      <div className={classes.buttonContent}>
        <AccessTimeIcon />
        <div
          className={cx(classes.containerDates, {
            [classes.containerDatesCondensed]: isCondensed
          })}
        >
          <div
            className={cx(classes.timeContainer, {
              [classes.timeContainerCondensed]: isCondensed
            })}
          >
            <Typography
              className={classes.label}
              component="div"
              variant="caption"
            >
              {t(labelFrom)}:
            </Typography>

            <Typography
              className={classes.date}
              component="div"
              variant="caption"
            >
              {format({
                date: customTimePeriod.start,
                formatString: dateTimeFormat
              })}
            </Typography>
          </div>
          <div
            className={cx(classes.timeContainer, {
              [classes.timeContainerCondensed]: isCondensed
            })}
          >
            <Typography
              className={classes.label}
              component="div"
              variant="caption"
            >
              {t(labelTo)}:
            </Typography>

            <Typography
              className={classes.date}
              component="div"
              variant="caption"
            >
              {format({
                date: customTimePeriod.end,
                formatString: dateTimeFormat
              })}
            </Typography>
          </div>
        </div>
      </div>
    </Button>
  );
};

export default CompactCustomTimePeriod;
