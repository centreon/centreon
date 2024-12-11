import { Legend as LegendComponent } from '../Legend';
import { LegendProps } from '../Legend/models';

import { BarStackProps } from './models';

const DefaultLengd = ({ scale, direction }: LegendProps): JSX.Element => (
  <LegendComponent direction={direction} scale={scale} />
);

const ResponsiveBarStack = ({
  title,
  data,
  width,
  height,
  size = 72,
  onSingleBarClick,
  displayLegend = true,
  TooltipContent,
  Legend = DefaultLengd,
  unit = 'number',
  displayValues,
  variant = 'vertical',
  legendDirection = 'column',
  tooltipProps = {}
}: BarStackProps & { height: number; width: number }): JSX.Element => {
  console.log(data);

  return <div />;
};

export default ResponsiveBarStack;
