import AdaptativeTypography from './AdaptativeTypography';

const Text = (): JSX.Element => {
  return (
    <div
      style={{
        display: 'grid',
        gridTemplateColumns: '1fr 1fr'
      }}
    >
      <AdaptativeTypography text="Hello world" />
      <AdaptativeTypography text="Hello world heyy" variant="h2" />
    </div>
  );
};

export default Text;
