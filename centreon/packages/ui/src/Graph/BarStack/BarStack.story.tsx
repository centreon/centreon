import BarStack from './BarStack';
import type { BarStackProps, BarType } from './models';

const data = [
  { color: '#88B922', label: 'Ok', value: 148 },
  { color: '#999999', label: 'Unknown', value: 13 },
  { color: '#F7931A', label: 'Warning', value: 16 },
  { color: '#FF6666', label: 'Down', value: 62 }
];

const TooltipContent = ({ label, color, value }: BarType): JSX.Element => (
  <div data-testid={`tooltip-${label}`} style={{ color }}>
    {label} : {value}
  </div>
);

interface Props extends Partial<Omit<BarStackProps, 'data' | 'TooltipContent'>> {
  height?: string;
  tooltip?: boolean;
  width?: string;
}

export const Default = ({ height = '400px', tooltip = false, width = '400px', ...props }: Props): JSX.Element => (
  <div style={{ height, width }}>
    <BarStack {...props} data={data} TooltipContent={tooltip ? TooltipContent : undefined} />
  </div>
);
