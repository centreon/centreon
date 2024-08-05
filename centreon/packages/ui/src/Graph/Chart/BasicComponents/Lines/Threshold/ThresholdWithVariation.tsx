import { curveBasis } from '@visx/curve';
import { LinePath } from '@visx/shape';

import { useTheme } from '@mui/material/styles';

import { TimeValue } from '../../../../common/timeSeries/models';

import BasicThreshold from './BasicThreshold';

interface Props {
  curve: 'linear' | 'natural' | 'step';
  getX: (timeValue: TimeValue) => number;
  getY0Variation: (timeValue: TimeValue) => number;
  getY1Variation: (timeValue: TimeValue) => number;
  graphHeight: number;
  lineColorY0: string;
  lineColorY1: string;
  timeSeries: Array<TimeValue>;
}

const ThresholdWithVariation = ({
  timeSeries,
  getX,
  getY0Variation,
  getY1Variation,
  graphHeight,
  lineColorY1,
  lineColorY0,
  curve
}: Props): JSX.Element => {
  const theme = useTheme();

  const props = {
    curve: curveBasis,
    data: timeSeries,
    stroke: theme.palette.secondary.main,
    strokeDasharray: 5,
    strokeOpacity: 0.9,
    x: getX
  };

  return (
    <>
      <BasicThreshold
        curve={curve}
        fillAboveArea={lineColorY1}
        fillBelowArea={lineColorY0}
        getX={getX}
        getY0={getY0Variation}
        getY1={getY1Variation}
        graphHeight={graphHeight}
        id="ThresholdWithVariation"
        timeSeries={timeSeries}
      />

      <LinePath {...props} y={getY0Variation} />
      <LinePath {...props} y={getY1Variation} />
    </>
  );
};

export default ThresholdWithVariation;
