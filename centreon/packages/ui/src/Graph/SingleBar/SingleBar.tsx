import { ParentSize } from '../..';

import ResponsiveSingleBar from './ResponsiveSingleBar';
import { SingleBarProps } from './models';

const SingleBar = ({ data, ...props }: SingleBarProps): JSX.Element | null => {
  if (!data) {
    return null;
  }

  return (
    <ParentSize>
      {({ width, height }) => (
        <ResponsiveSingleBar
          {...props}
          data={data}
          height={height}
          width={width}
        />
      )}
    </ParentSize>
  );
};

export default SingleBar;
