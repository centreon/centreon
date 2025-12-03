import { FluidTypography } from '@centreon/ui';
import { ReactElement } from 'react';

const Data = (): ReactElement => {
  return (
    <div
      style={{
        display: 'grid',
        gridTemplateColumns: 'repeat(2, minmax(60px, auto))'
      }}
    >
      <FluidTypography text="Hello world" />
      <FluidTypography text="Hello world" variant="h2" />
    </div>
  );
};

export default Data;
