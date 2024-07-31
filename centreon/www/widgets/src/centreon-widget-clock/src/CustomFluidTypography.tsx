import { ParentSize } from '@centreon/ui';

interface Props {
  children: (props) => JSX.Element;
}

const CustomFluidTypography = ({ children }: Props): JSX.Element => {
  return (
    <ParentSize>
      {({ height, width }) =>
        children({
          fontSize: Math.min(height, width) / (height > width ? 3.5 : 3.2),
          height,
          width
        })
      }
    </ParentSize>
  );
};

export default CustomFluidTypography;
