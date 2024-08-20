import { equals, isNil } from 'ramda';

import { getUnits, getYScale } from '../timeSeries';
import { Line } from '../timeSeries/models';
import { Thresholds as ThresholdsModel } from '../models';

import { ThresholdLine } from './ThresholdLine';

interface Props {
  displayedLines: Array<Line>;
  hideTooltip: () => void;
  isHorizontal?: boolean;
  leftScale: (value: number) => number;
  rightScale: (value: number) => number;
  showTooltip: (props) => void;
  thresholdUnit?: string;
  thresholds: ThresholdsModel;
  width: number;
}

const Thresholds = ({
  thresholds,
  leftScale,
  rightScale,
  width,
  displayedLines,
  thresholdUnit,
  showTooltip,
  hideTooltip,
  isHorizontal = true
}: Props): JSX.Element => {
  const [firstUnit, secondUnit, thirdUnit] = getUnits(
    displayedLines as Array<Line>
  );

  const shouldUseRightScale = equals(thresholdUnit, secondUnit);

  const yScale = shouldUseRightScale
    ? rightScale
    : getYScale({
        hasMoreThanTwoUnits: !isNil(thirdUnit),
        invert: null,
        leftScale,
        rightScale,
        secondUnit,
        unit: firstUnit
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
