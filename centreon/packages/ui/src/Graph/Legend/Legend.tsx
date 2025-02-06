import { LegendOrdinal } from '@visx/legend';
import { scaleOrdinal } from '@visx/scale';
import { equals } from 'ramda';

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
      className={classes.container}
      direction={direction}
      labelMargin={equals(direction, 'row') ? '0 12px 0 0' : '0 0 0 0'}
      scale={legendScale}
    />
  );
};

export default Legend;
