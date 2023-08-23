import { Responsive } from '@visx/visx';

import { SingleBarProps } from './models';
import ResponsiveSingleBar from './ResponsiveSingleBar';

const SingleBar = ({ data, ...props }: SingleBarProps): JSX.Element | null => {
  if (!props.data) {
    return null;
  }

  return (
    <Responsive.ParentSizeModern>
      {({ width, height }) => (
        <ResponsiveSingleBar
          {...props}
          data={data}
          height={height}
          width={width}
        />
      )}
    </Responsive.ParentSizeModern>
  );
};

export default SingleBar;
