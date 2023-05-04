import { FluidTypography } from '@centreon/ui';

const Text = (): JSX.Element => {
  return (
    <div
      style={{
        display: 'grid',
        gridTemplateColumns: 'repeat(2, minmax(60px, auto))'
      }}
    >
      <FluidTypography text="Hello world" />
      <FluidTypography text="Hello world heyy" variant="h2" />
    </div>
  );
};

export default Text;
