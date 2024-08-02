import { ParentSize } from '@centreon/ui';

interface Props {
  children: (props) => JSX.Element;
  forceHeight?: number;
  forceWidth?: number;
}

const CustomFluidTypography = ({
  children,
  forceWidth,
  forceHeight
}: Props): JSX.Element => {
  return (
    <ParentSize>
      {({ height, width }) =>
        children({
          fontSize:
            Math.min(forceHeight || height, forceWidth || width) /
            ((forceHeight || height) > (forceWidth || width) ? 3.7 : 3.4),
          height: forceHeight || height,
          width: forceWidth || width
        })
      }
    </ParentSize>
  );
};

export default CustomFluidTypography;
