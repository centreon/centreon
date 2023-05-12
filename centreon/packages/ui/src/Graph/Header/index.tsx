import { isNil } from 'ramda';

import Typography from '@mui/material/Typography';

import { useLocaleDateTimeFormat } from '@centreon/ui';

import { useStyles } from '../Graph.styles';
import { HeaderGraph } from '../models';

interface Props {
  displayTimeTick?: boolean;
  header?: HeaderGraph;
  timeTick?: Date;
  title: string;
}

const Header = ({
  title,
  timeTick,
  displayTimeTick = true,
  header
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { toDateTime } = useLocaleDateTimeFormat();
  const time =
    displayTimeTick && !isNil(timeTick) ? toDateTime(timeTick) : null;

  const displayTitle = header?.displayTitle ?? true;

  return (
    <div className={classes.header}>
      <div />
      {displayTitle && (
        <div>
          <Typography align="center" variant="body1">
            {title}
          </Typography>

          <Typography align="center" style={{ height: 20 }} variant="body1">
            {time}
          </Typography>
        </div>
      )}
      {header?.extraComponent}
    </div>
  );
};
export default Header;
