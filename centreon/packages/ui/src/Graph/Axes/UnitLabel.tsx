import { commonTickLabelProps } from '../common';

interface UnitLabelProps {
  unit: string;
  x: number;
}

const UnitLabel = ({ x, unit }: UnitLabelProps): JSX.Element => (
  <text
    fontFamily={commonTickLabelProps.fontFamily}
    fontSize={commonTickLabelProps.fontSize}
    x={x}
    y={-8}
  >
    {unit}
  </text>
);

export default UnitLabel;
