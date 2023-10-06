import { Responsive } from '@visx/visx';

import { ParentSize } from '../..';

import { SingleBarProps } from './models';
import ResponsiveSingleBar from './ResponsiveSingleBar';

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
