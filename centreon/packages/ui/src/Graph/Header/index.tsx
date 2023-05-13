import { MutableRefObject } from 'react';

import { ScaleLinear } from 'd3-scale';
import { isNil } from 'ramda';

import Typography from '@mui/material/Typography';

import { useLocaleDateTimeFormat } from '@centreon/ui';

import { useStyles } from '../Graph.styles';
import useAnchorPoint from '../InteractiveComponents/AnchorPoint/useAnchorPoint';
import { GraphHeader } from '../models';
import { TimeValue } from '../timeSeries/models';

interface Props {
  displayTimeTick?: boolean;
  graphSvgRef: MutableRefObject<SVGSVGElement | null>;
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
  graphSvgRef,
  xScale
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { toDateTime } = useLocaleDateTimeFormat();

  const { timeTick } = useAnchorPoint({ graphSvgRef, timeSeries, xScale });
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
