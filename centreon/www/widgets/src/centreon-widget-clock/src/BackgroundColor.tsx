import { useClockStyles } from './Clock.styles';

const BackgroundColor = ({
  hasDescription,
  backgroundColor
}: {
  backgroundColor?: string;
  hasDescription: boolean;
}): JSX.Element => {
  const { classes } = useClockStyles();

  return (
    <div
      className={classes.background}
      data-hasDescription={hasDescription}
      style={{
        backgroundColor: backgroundColor ?? '#255891'
      }}
    />
  );
};

export default BackgroundColor;
