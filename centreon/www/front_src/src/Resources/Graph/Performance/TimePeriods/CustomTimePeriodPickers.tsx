import { MouseEvent, useState } from 'react';

import dayjs from 'dayjs';
import isSameOrAfter from 'dayjs/plugin/isSameOrAfter';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import AccessTimeIcon from '@mui/icons-material/AccessTime';
import { Button, Typography } from '@mui/material';

import { dateTimeFormat, useLocaleDateTimeFormat } from '@centreon/ui';

import {
  CustomTimePeriod,
  CustomTimePeriodProperty
} from '../../../Details/tabs/Graph/models';
import {
  labelCompactTimePeriod,
  labelFrom,
  labelTo
} from '../../../translatedLabels';

import { AnchorReference } from './models';
import PopoverCustomTimePeriodPickers from './PopoverCustomTimePeriodPicker';

interface AcceptDateProps {
  date: Date;
  property: CustomTimePeriodProperty;
}

interface Props {
  acceptDate: (props: AcceptDateProps) => void;
  customTimePeriod: CustomTimePeriod;
  isCompact: boolean;
}

dayjs.extend(isSameOrAfter);

const useStyles = makeStyles()((theme) => ({
  button: {
    height: '100%',
    padding: theme.spacing(0, 0.5)
  },
  buttonContent: {
    alignItems: 'center',
    columnGap: theme.spacing(1),
    display: 'grid',
    gridTemplateColumns: 'min-content auto'
  },
  compactFromTo: {
    display: 'flex',
    flexDirection: 'column',
    padding: theme.spacing(0.5, 0, 0.5, 0)
  },
  date: {
    display: 'flex'
  },
  dateLabel: {
    display: 'flex',
    flex: 1,
    paddingRight: 4
  },
  fromTo: {
    alignItems: 'center',
    columnGap: theme.spacing(0.5),
    display: 'grid',
    gridTemplateColumns: 'repeat(2, auto)'
  },
  minimalPickers: {
    alignItems: 'center',
    columnGap: theme.spacing(1),
    display: 'grid',
    gridTemplateColumns: 'min-content auto'
  },
  picker: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1),
    justifyItems: 'center',
    padding: theme.spacing(1, 2)
  },
  timeContainer: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row'
  }
}));

const CustomTimePeriodPickers = ({
  customTimePeriod,
  acceptDate,
  isCompact: isMinimalWidth
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const [anchorEl, setAnchorEl] =
    useState<AnchorReference['anchorEl']>(undefined);

  const { format } = useLocaleDateTimeFormat();

  const openPopover = (event: MouseEvent): void => {
    setAnchorEl(event.currentTarget);
  };

  const closePopover = (): void => {
    setAnchorEl(undefined);
  };

  const displayPopover = Boolean(anchorEl);

  return (
    <>
      <Button
        aria-label={t(labelCompactTimePeriod) as string}
        className={classes.button}
        color="primary"
        data-testid={labelCompactTimePeriod}
        variant="outlined"
        onClick={openPopover}
      >
        <div className={classes.buttonContent}>
          <AccessTimeIcon />
          <div
            className={isMinimalWidth ? classes.compactFromTo : classes.fromTo}
          >
            <div className={classes.timeContainer}>
              <div className={classes.dateLabel}>
                <Typography variant="caption">{t(labelFrom)}:</Typography>
              </div>
              <div className={classes.date}>
                <Typography variant="caption">
                  {format({
                    date: customTimePeriod.start,
                    formatString: dateTimeFormat
                  })}
                </Typography>
              </div>
            </div>
            <div className={classes.timeContainer}>
              <div className={classes.dateLabel}>
                <Typography variant="caption">{t(labelTo)}:</Typography>
              </div>
              <div className={classes.date}>
                <Typography variant="caption">
                  {format({
                    date: customTimePeriod.end,
                    formatString: dateTimeFormat
                  })}
                </Typography>
              </div>
            </div>
          </div>
        </div>
      </Button>
      <PopoverCustomTimePeriodPickers
        acceptDate={acceptDate}
        anchorReference="anchorEl"
        classNamePicker={classes.picker}
        customTimePeriod={customTimePeriod}
        open={displayPopover}
        reference={{ anchorEl }}
        onClose={closePopover}
      />
    </>
  );
};

export default CustomTimePeriodPickers;
