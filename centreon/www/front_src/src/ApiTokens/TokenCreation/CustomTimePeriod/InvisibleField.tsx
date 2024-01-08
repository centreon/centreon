import { useStyles } from '../tokenCreation.styles';

const InvisibleField = (): JSX.Element => {
  const { classes } = useStyles();

  return <div className={classes.invisible}>Customize</div>;
};

export default InvisibleField;
