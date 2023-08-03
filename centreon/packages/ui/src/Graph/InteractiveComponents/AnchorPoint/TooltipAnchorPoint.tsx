import { Tooltip } from '@visx/visx';

import { Typography, useTheme } from '@mui/material';

import useTooltipAnchorPoint from './useTooltipAnchorPoint';
import { TooltipAnchorModel } from './models';

const baseStyles = {
  ...Tooltip.defaultStyles,
  textAlign: 'center'
};

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
  const theme = useTheme();

  const {
    tooltipDataAxisX,
    tooltipDataAxisYLeft,
    tooltipLeftAxisX,
    tooltipLeftAxisYLeft,
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

  const cardStyles = {
    backgroundColor: theme.palette.background.paper,
    color: theme.palette.text.primary,
    padding: theme.spacing(0.25, 0.5)
  };

  return (
    <>
      {tooltipDataAxisX && (
        <Tooltip.Tooltip
          left={tooltipLeftAxisX}
          style={{
            ...baseStyles,
            ...cardStyles,
            transform: 'translateX(-70%)'
          }}
          top={0}
        >
          <Typography variant="caption">{tooltipDataAxisX}</Typography>
        </Tooltip.Tooltip>
      )}
      {tooltipDataAxisYLeft && (
        <Tooltip.Tooltip
          left={tooltipLeftAxisYLeft}
          style={{
            ...baseStyles,
            ...cardStyles,
            transform: 'translateX(-70%) translateY(-100%)'
          }}
          top={tooltipTopAxisYLeft}
        >
          <Typography variant="caption">{tooltipDataAxisYLeft}</Typography>
        </Tooltip.Tooltip>
      )}
      {tooltipDataAxisYRight && (
        <Tooltip.Tooltip
          left={tooltipLeftAxisYRight}
          style={{
            ...baseStyles,
            ...cardStyles,
            transform: 'translateX(-70%)  translateY(-80%)'
          }}
          top={tooltipTopAxisYRight}
        >
          <Typography variant="caption">{tooltipDataAxisYRight}</Typography>
        </Tooltip.Tooltip>
      )}
    </>
  );
};

export default TooltipAnchorPoint;
