import { LegendOrdinal } from '@visx/legend';
import { scaleOrdinal } from '@visx/scale';

import { useStyles } from './Legend.styles';
import { LegendProps } from './models';

const Legend = ({ scale, direction = 'column' }: LegendProps): JSX.Element => {
  const { classes } = useStyles();

  const legendScale = scaleOrdinal({
    domain: scale.domain,
    range: scale.range
  });

  return (
    <LegendOrdinal
      direction={direction}
      scale={legendScale}
      className={classes.container}
    />
  );
};

export default Legend;
