import PickersStartEndDate from './PopoverCustomTimePeriod/PickersStartEndDate';
import {
  AcceptDateProps,
  PickersStartEndDateDirection,
  PickersStartEndDateProps
} from './PopoverCustomTimePeriod/models';

interface Props {
  changeDate: (props: AcceptDateProps) => void;
  endDate: Date;
  startDate: Date;
}

const SimpleCustomTimePeriod = ({
  startDate,
  endDate,
  changeDate,
  ...rest
}: Props &
  Partial<
    Omit<PickersStartEndDateProps, 'startDate' | 'endDate' | 'changeDate'>
  >): JSX.Element => {
  return (
    <PickersStartEndDate
      changeDate={changeDate}
      direction={PickersStartEndDateDirection.row}
      endDate={endDate}
      startDate={startDate}
      {...rest}
    />
  );
};

export default SimpleCustomTimePeriod;
