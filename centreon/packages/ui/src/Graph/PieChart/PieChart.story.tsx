import type { ArcType, PieProps } from './models';
import PieChart from './PieChart';

export const pieData = [
  { color: '#88B922', label: 'Ok', value: 148 },
  { color: '#999999', label: 'Unknown', value: 13 },
  { color: '#F7931A', label: 'Warning', value: 16 },
  { color: '#FF6666', label: 'Down', value: 62 }
];

const TooltipContent = ({ label, color, value }: ArcType): JSX.Element => (
  <div data-testid={`tooltip-${label}`} style={{ color }}>
    {label} : {value}
  </div>
);

interface Props
  extends Partial<Omit<PieProps, 'data' | 'TooltipContent' | 'onArcClick'>> {
  height?: string;
  tooltip?: boolean;
  width?: string;
}

export const Default = ({
  height = '500px',
  // The default of the chart, required by its props type.
  opacity = 1,
  tooltip = false,
  width = '500px',
  ...props
}: Props): JSX.Element => (
  <div style={{ height, width }}>
    <PieChart
      {...props}
      data={pieData}
      opacity={opacity}
      TooltipContent={tooltip ? TooltipContent : undefined}
    />
  </div>
);
