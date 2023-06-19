import Paper from '@mui/material/Paper';
import { Typography } from '@mui/material';

import useTooltipAnchorPoint from './useTooltipAnchorPoint';
import { useStyles } from './AnchorPoint.styles';
import { TooltipAnchorModel } from './models';

const TooltipAnchorPoint = ({
  timeSeries,
  xScale,
  graphHeight,
  leftScale,
  rightScale,
  graphWidth,
  lines,
  baseAxis
}: TooltipAnchorModel): JSX.Element => {
  const {
    tooltipDataAxisX,
    tooltipDataAxisYLeft,
    tooltipLeftAxisX,
    tooltipLeftAxisYLeft,
    tooltipTopAxisX,
    tooltipTopAxisYLeft,
    tooltipDataAxisYRight,
    tooltipTopAxisYRight,
    tooltipLeftAxisYRight
  } = useTooltipAnchorPoint({
    baseAxis,
    graphHeight,
    graphWidth,
    leftScale,
    lines,
    rightScale,
    timeSeries,
    xScale
  });
  const { classes } = useStyles({
    tooltipLeftAxisX,
    tooltipLeftAxisYLeft,
    tooltipLeftAxisYRight,
    tooltipTopAxisX,
    tooltipTopAxisYLeft,
    tooltipTopAxisYRight
  });

  return (
    <>
      {tooltipDataAxisYLeft && (
        <Paper className={classes.tooltipAxisLeftY}>
          <Typography variant="caption">{tooltipDataAxisYLeft}</Typography>
        </Paper>
      )}
      {tooltipDataAxisX && (
        <Paper className={classes.tooltipAxisBottom}>
          <Typography variant="caption">{tooltipDataAxisX}</Typography>
        </Paper>
      )}
      {tooltipDataAxisYRight && (
        <Paper className={classes.tooltipLeftAxisRightY}>
          <Typography variant="caption">{tooltipDataAxisYRight}</Typography>
        </Paper>
      )}
    </>
  );
};

export default TooltipAnchorPoint;
