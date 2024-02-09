import { LegendOrdinal } from '@visx/legend';
import { scaleOrdinal } from '@visx/scale';

import { LegendProps } from './models';

const Legend = ({ scale, configuration }: LegendProps): JSX.Element => {
  const legendScale = scaleOrdinal({
    domain: scale.domain,
    range: scale.range
  });

  return (
    <LegendOrdinal
      direction={configuration.direction}
      labelMargin="0 16px 0 0"
      scale={legendScale}
    />
  );
};

export default Legend;
