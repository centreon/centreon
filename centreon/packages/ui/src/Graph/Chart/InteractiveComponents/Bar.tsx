import { AddSVGProps } from '@visx/shape/lib/types';
import { Shape } from '@visx/visx';

interface BarProps {
  className?: string;
  innerRef?: React.Ref<SVGRectElement>;
  open?: boolean;
}

const Bar = ({
  open = true,
  ...restProps
}: AddSVGProps<BarProps, SVGRectElement>): JSX.Element | null => {
  if (!open) {
    return null;
  }

  return <Shape.Bar {...restProps} />;
};

export default Bar;
