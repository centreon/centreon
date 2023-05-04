import { isNil } from 'ramda';

import Typography from '@mui/material/Typography';

import useLocaleDateTimeFormat from '../../utils/useLocaleDateTimeFormat';

interface Props {
  displayTimeTick?: boolean;
  timeTick?: Date;
  title: string;
}

const Header = ({
  title,
  timeTick,
  displayTimeTick = true
}: Props): JSX.Element => {
  const { toDateTime } = useLocaleDateTimeFormat();
  const time =
    displayTimeTick && !isNil(timeTick) ? toDateTime(timeTick) : null;

  return (
    <>
      <Typography align="center" variant="body1">
        {title}
      </Typography>

      <Typography align="center" style={{ height: 20 }} variant="body1">
        {time}
      </Typography>
    </>
  );
};
export default Header;
