import { commonTickLabelProps } from '../utils';

interface UnitLabelProps {
  unit: string;
  x: number;
  y?: number;
}

const UnitLabel = ({ x, y = 16, unit }: UnitLabelProps): JSX.Element => (
  <text
    fontFamily={commonTickLabelProps.fontFamily}
    fontSize={commonTickLabelProps.fontSize}
    x={x}
    y={-y}
  >
    {unit}
  </text>
);

export default UnitLabel;
