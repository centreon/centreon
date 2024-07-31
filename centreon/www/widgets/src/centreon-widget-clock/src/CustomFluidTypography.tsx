import { ParentSize } from '@centreon/ui';

interface Props {
  children: (fontZise: number) => JSX.Element;
}

const CustomFluidTypography = ({ children }: Props): JSX.Element => {
  return (
    <ParentSize>
      {({ height, width }) =>
        children(Math.min(height, width) / (height > width ? 3 : 2.2))
      }
    </ParentSize>
  );
};

export default CustomFluidTypography;
