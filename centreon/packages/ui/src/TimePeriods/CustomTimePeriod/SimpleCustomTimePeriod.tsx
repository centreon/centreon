import PickersStartEndDate from './PopoverCustomTimePeriod/PickersStartEndDate';
import {
  AcceptDateProps,
  PickersStartEndDateDirection
} from './PopoverCustomTimePeriod/models';

interface Props {
  changeDate: (props: AcceptDateProps) => void;
  endDate: Date;
  startDate: Date;
}

const SimpleCustomTimePeriod = ({
  startDate,
  endDate,
  changeDate
}: Props): JSX.Element => {
  return (
    <PickersStartEndDate
      changeDate={changeDate}
      direction={PickersStartEndDateDirection.row}
      endDate={endDate}
      startDate={startDate}
    />
  );
};

export default SimpleCustomTimePeriod;
