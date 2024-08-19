import { equals } from 'ramda';
import { ScaleLinear } from 'd3-scale';

import { getUnits, getYScale } from '../timeSeries';
import { Line } from '../timeSeries/models';
import { Thresholds as ThresholdsModel } from '../models';

import { ThresholdLine } from './ThresholdLine';

interface Props {
  displayedLines: Array<Line>;
  hideTooltip: () => void;
  isHorizontal?: boolean;
  showTooltip: (props) => void;
  thresholdUnit?: string;
  thresholds: ThresholdsModel;
  width: number;
  yScalesPerUnit: Record<string, ScaleLinear<number, number>>;
}

const Thresholds = ({
  thresholds,
  width,
  displayedLines,
  thresholdUnit,
  showTooltip,
  hideTooltip,
  yScalesPerUnit,
  isHorizontal = true
}: Props): JSX.Element => {
  const [firstUnit, secondUnit] = getUnits(displayedLines as Array<Line>);

  const shouldUseRightScale =
    thresholdUnit && equals(thresholdUnit, secondUnit);

  const yScale = shouldUseRightScale
    ? yScalesPerUnit[secondUnit]
    : getYScale({
        invert: null,
        unit: firstUnit,
        yScalesPerUnit
      });

  return (
    <>
      {thresholds.warning.map(({ value, label }) => (
        <ThresholdLine
          hideTooltip={hideTooltip}
          isHorizontal={isHorizontal}
          key={`warning-${value}`}
          label={label}
          showTooltip={showTooltip}
          thresholdType="warning"
          value={value}
          width={width}
          yScale={yScale}
        />
      ))}
      {thresholds.critical.map(({ value, label }) => (
        <ThresholdLine
          hideTooltip={hideTooltip}
          isHorizontal={isHorizontal}
          key={`critical-${value}`}
          label={label}
          showTooltip={showTooltip}
          thresholdType="critical"
          value={value}
          width={width}
          yScale={yScale}
        />
      ))}
    </>
  );
};

export default Thresholds;
