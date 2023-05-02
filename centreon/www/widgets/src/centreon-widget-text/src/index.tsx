import { FluidTypography } from '@centreon/ui';

const Text = (): JSX.Element => {
  return (
    <div
      style={{
        display: 'grid',
        gridTemplateColumns: '1fr 1fr'
      }}
    >
      <FluidTypography text="Hello world" />
      <FluidTypography text="Hello world heyy" variant="h2" />
    </div>
  );
};

export default Text;
