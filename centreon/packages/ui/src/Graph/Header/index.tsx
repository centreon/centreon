import { ScaleLinear } from 'd3-scale';
import { isNil } from 'ramda';

import Typography from '@mui/material/Typography';

import { useLocaleDateTimeFormat, useMemoComponent } from '@centreon/ui';

import { useStyles } from '../Graph.styles';
import useTickGraph from '../InteractiveComponents/AnchorPoint/useTickGraph';
import { GraphHeader } from '../models';
import { TimeValue } from '../timeSeries/models';

interface Props {
  displayTimeTick?: boolean;
  header?: GraphHeader;
  timeSeries: Array<TimeValue>;
  title: string;
  xScale: ScaleLinear<number, number>;
}

const Header = ({
  title,
  displayTimeTick = true,
  header,
  timeSeries,
  xScale
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { toDateTime } = useLocaleDateTimeFormat();

  const { tickAxisBottom } = useTickGraph({
    timeSeries,
    xScale
  });
  const time =
    displayTimeTick && !isNil(tickAxisBottom)
      ? toDateTime(tickAxisBottom)
      : null;

  const displayTitle = header?.displayTitle ?? true;

  return useMemoComponent({
    Component: (
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
    ),

    memoProps: [time, timeSeries, xScale, displayTimeTick, title, header]
  });
};

export default Header;
