import { Responsive } from '@visx/visx';

import { SingleBarProps } from './models';
import ResponsiveSingleBar from './ResponsiveSingleBar';

const SingleBar = (props: SingleBarProps): JSX.Element => {
  return (
    <Responsive.ParentSizeModern>
      {({ width, height }) => (
        <ResponsiveSingleBar {...props} height={height} width={width} />
      )}
    </Responsive.ParentSizeModern>
  );
};

export default SingleBar;
