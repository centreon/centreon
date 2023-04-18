import Typography from '@mui/material/Typography';

import useLocaleDateTimeFormat from '../utils/useLocaleDateTimeFormat';

const Header = ({ title, timeTick }: any): JSX.Element => {
  const { toDateTime } = useLocaleDateTimeFormat();

  return (
    <>
      <Typography align="center" variant="body1">
        {title}
      </Typography>

      <Typography align="center" style={{ height: 20 }} variant="body1">
        {timeTick && toDateTime(timeTick)}
      </Typography>
    </>
  );
};
export default Header;
