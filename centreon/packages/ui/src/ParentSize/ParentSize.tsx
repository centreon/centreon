import { ReactElement } from 'react';

import { Responsive } from '@visx/visx';

type ParentSizeState = {
  height: number;
  left: number;
  top: number;
  width: number;
};

type Props = {
  children: (args: ParentSizeState) => ReactElement;
};

const ParentSize = ({ children, ...props }: Props): JSX.Element => {
  return (
    <Responsive.ParentSize {...props} debounceTime={0}>
      {children}
    </Responsive.ParentSize>
  );
};

export default ParentSize;
