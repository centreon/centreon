import { equals, isNil } from 'ramda';

import { getUnits, getYScale } from '../../common/timeSeries';
import { Line } from '../../common/timeSeries/models';
import { Thresholds as ThresholdsModel } from '../../common/models';

import { ThresholdLine } from './ThresholdLine';

interface Props {
  displayedLines: Array<Line>;
  hideTooltip: () => void;
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
  hideTooltip
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
