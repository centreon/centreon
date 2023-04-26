import { MouseEvent, useEffect, useState } from 'react';

import dayjs from 'dayjs';
import isSameOrAfter from 'dayjs/plugin/isSameOrAfter';
import { useAtomValue, useUpdateAtom } from 'jotai/utils';
import { makeStyles } from 'tss-react/mui';

import AccessTimeIcon from '@mui/icons-material/AccessTime';
import { Button, Typography } from '@mui/material';

import { dateTimeFormat, useLocaleDateTimeFormat } from '@centreon/ui';

import { labelCompactTimePeriod } from '../labels';
import { CustomTimePeriodProperty, LabelTimePeriodPicker } from '../models';
import {
  changeCustomTimePeriodDerivedAtom,
  customTimePeriodAtom
} from '../timePeriodAtoms';

import PopoverCustomTimePeriodPickers from './PopoverCustomTimePeriodPicker';

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
  error: {
    textAlign: 'center'
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

interface AcceptDateProps {
  date: Date;
  property: CustomTimePeriodProperty;
}

interface Props {
  getDate?: (date) => void;
  isCompact: boolean;
  labelTimePeriodPicker?: LabelTimePeriodPicker;
}

const CustomTimePeriodPickers = ({
  isCompact: isMinimalWidth,
  labelTimePeriodPicker = { labelEnd: 'To', labelFrom: 'From' },
  getDate
}: Props): JSX.Element => {
  const { classes } = useStyles();

  const { format } = useLocaleDateTimeFormat();

  const [anchorEl, setAnchorEl] = useState<Element | undefined>(undefined);

  const customTimePeriod = useAtomValue(customTimePeriodAtom);

  const changeCustomTimePeriod = useUpdateAtom(
    changeCustomTimePeriodDerivedAtom
  );

  const displayPopover = Boolean(anchorEl);

  const openPopover = (event: MouseEvent): void => {
    setAnchorEl(event.currentTarget);
  };

  const closePopover = (): void => {
    setAnchorEl(undefined);
  };

  const changeDate = ({ property, date }): void =>
    changeCustomTimePeriod({ date, property });

  useEffect(() => {
    getDate?.(customTimePeriod);
  }, [customTimePeriod]);

  return (
    <>
      <Button
        aria-label={labelCompactTimePeriod}
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
                <Typography variant="caption">
                  {labelTimePeriodPicker.labelFrom}:
                </Typography>
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
                <Typography variant="caption">
                  {labelTimePeriodPicker.labelEnd}:
                </Typography>
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
        customStyle={{
          classNameError: classes.error,
          classNamePicker: classes.picker
        }}
        labelTimePeriodPicker={labelTimePeriodPicker}
        pickersData={{
          acceptDate: changeDate,
          customTimePeriod,
          maxDatePickerStartInput: customTimePeriod.end,
          minDatePickerEndInput: customTimePeriod.start
        }}
        popoverData={{
          anchorEl,
          anchorReference: 'anchorEl',
          onClose: closePopover,
          open: displayPopover
        }}
      />
    </>
  );
};

export default CustomTimePeriodPickers;
