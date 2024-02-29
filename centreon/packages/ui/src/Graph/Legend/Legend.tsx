import { LegendOrdinal } from '@visx/legend';
import { scaleOrdinal } from '@visx/scale';

import { LegendProps } from './models';

const Legend = ({ scale, direction = 'column' }: LegendProps): JSX.Element => {
  const legendScale = scaleOrdinal({
    domain: scale.domain,
    range: scale.range
  });

  return (
    <LegendOrdinal
      direction={direction}
      labelMargin="0 16px 0 0"
      scale={legendScale}
    />
  );
};

export default Legend;
