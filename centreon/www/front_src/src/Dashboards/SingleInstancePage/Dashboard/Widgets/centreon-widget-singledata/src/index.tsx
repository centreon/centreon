import { FluidTypography } from '@centreon/ui';

const Data = (): JSX.Element => {
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
