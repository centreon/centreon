
import { FluidTypography } from '@centreon/ui';

interface Props {
  children: JSX.Element;
}

const Data = ({ children }: Props): JSX.Element => {
  return (
    <div
      style={{
        display: 'grid',
        gridTemplateColumns: 'repeat(2, minmax(60px, auto))'
      }}
    >
      <FluidTypography text="Hello world" />
      {children}
    </div>
  );
};

export default Data;
