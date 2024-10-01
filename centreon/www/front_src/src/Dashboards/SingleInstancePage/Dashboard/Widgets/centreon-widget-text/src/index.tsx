import { FluidTypography } from '@centreon/ui';

interface Props {
  children: JSX.Element;
}

const Text = ({ children }: Props): JSX.Element => {
  return (
    <div
      style={{
        display: 'grid',
        gridTemplateColumns: 'repeat(2, minmax(60px, auto))'
      }}
    >
      <FluidTypography text="Hello world" />
      <FluidTypography text="Hello world" variant="h2" />
      {children}
    </div>
  );
};

export default Text;
