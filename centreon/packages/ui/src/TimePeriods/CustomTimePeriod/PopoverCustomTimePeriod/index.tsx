import { makeStyles } from 'tss-react/mui';

import { Popover } from '@mui/material';

import PickersStartEndDate from './PickersStartEndDate';
import {
  PickersData,
  PopoverData,
  defaultAnchorOrigin,
  defaultTransformOrigin
} from './models';
import usePickersStartEndDate from './usePickersStartEndDate';

const useStyles = makeStyles()((theme) => ({
  paper: {
    '& .MuiPopover-paper': {
      minWidth: 250,
      padding: theme.spacing(1)
    }
  }
}));

interface Props {
  pickersData: PickersData;
  popoverData: PopoverData;
}

const PopoverCustomTimePeriod = ({
  popoverData,
  pickersData
}: Props): JSX.Element => {
  const { classes } = useStyles();

  const { open, onClose } = popoverData;
  const { acceptDate, customTimePeriod } = pickersData;

  const { startDate, endDate, changeDate } = usePickersStartEndDate({
    acceptDate,
    customTimePeriod
  });

  const anchorEl = popoverData?.anchorEl;
  const anchorPosition = popoverData?.anchorPosition;
  const anchorOrigin = popoverData?.anchorOrigin;
  const transformOrigin = popoverData?.transformOrigin;
  const anchorReference = anchorPosition ? 'anchorPosition' : 'anchorEl';

  const disabled = {
    isDisabledEndPicker: pickersData?.isDisabledEndPicker,
    isDisabledStartPicker: pickersData?.isDisabledStartPicker
  };

  return (
    <Popover
      anchorEl={anchorEl}
      anchorOrigin={anchorOrigin ?? defaultAnchorOrigin}
      anchorPosition={anchorPosition}
      anchorReference={anchorReference}
      className={classes.paper}
      open={open}
      transformOrigin={transformOrigin ?? defaultTransformOrigin}
      onClose={onClose}
    >
      <PickersStartEndDate
        changeDate={changeDate}
        disabled={disabled}
        endDate={endDate}
        rangeEndDate={pickersData?.rangeEndDate}
        rangeStartDate={pickersData?.rangeStartDate}
        startDate={startDate}
      />
    </Popover>
  );
};

export default PopoverCustomTimePeriod;
