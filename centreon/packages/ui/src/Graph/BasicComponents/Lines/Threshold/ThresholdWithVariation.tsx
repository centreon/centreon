import { curveBasis } from '@visx/curve';
import { LinePath } from '@visx/shape';
import { ScaleLinear } from 'd3-scale';

import { useTheme } from '@mui/material/styles';

import { useMemoComponent } from '@centreon/ui';

import { TimeValue } from '../../../timeSeries/models';

import BasicThreshold from './BasicThreshold';
import { Data, FactorsVariation } from './models';
import { getVariationEnvelopThresholdData } from './helpers';

interface Props {
  dataY0: Data;
  dataY1: Data;
  factors: FactorsVariation;
  graphHeight: number;
  timeSeries: Array<TimeValue>;
  xScale: ScaleLinear<number, number>;
}

const ThresholdWithVariation = ({
  dataY0,
  dataY1,
  timeSeries,
  xScale,
  graphHeight,
  factors
}: Props): JSX.Element => {
  const theme = useTheme();

  const { getX, getY0, getY1, lineColorY0, lineColorY1 } =
    getVariationEnvelopThresholdData({
      dataY0,
      dataY1,
      factors,
      xScale
    });

  const props = {
    curve: curveBasis,
    data: timeSeries,
    stroke: theme.palette.secondary.main,
    strokeDasharray: 5,
    strokeOpacity: 0.8,
    x: getX
  };

  return useMemoComponent({
    Component: (
      <>
        <BasicThreshold
          fillAboveArea={lineColorY1}
          fillBelowArea={lineColorY0}
          getX={getX}
          getY0={getY0}
          getY1={getY1}
          graphHeight={graphHeight}
          timeSeries={timeSeries}
        />

        <LinePath {...props} y={getY0} />
        <LinePath {...props} y={getY1} />
      </>
    ),
    memoProps: [timeSeries, factors]
  });
};

export default ThresholdWithVariation;
