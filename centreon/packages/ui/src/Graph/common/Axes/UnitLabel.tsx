import SelectField from '../../../InputField/Select';
import { commonTickLabelProps } from '../utils';

import { useYAxisStyles } from './AxisStyles';

interface UnitLabelProps {
  onUnitChange?: (newUnit: string) => void;
  unit: string;
  units: Array<string>;
  x: number;
  y?: number;
}

const UnitLabel = ({
  x,
  y = 16,
  unit,
  onUnitChange,
  units
}: UnitLabelProps): JSX.Element => {
  const { classes } = useYAxisStyles();

  return onUnitChange ? (
    <foreignObject height={36} width={60} x={x - 20} y={-y * 2}>
      <div className={classes.unitContainer}>
        <SelectField
          className={classes.axisInput}
          dataTestId="unit-selector"
          options={units.map((unitOption) => ({
            id: unitOption,
            name: unitOption
          }))}
          selectedOptionId={unit}
          size="small"
          onChange={(e) => onUnitChange(e.target.value)}
        />
      </div>
    </foreignObject>
  ) : (
    <text
      fontFamily={commonTickLabelProps.fontFamily}
      fontSize={commonTickLabelProps.fontSize}
      x={x}
      y={-y}
    >
      {unit}
    </text>
  );
};

export default UnitLabel;
