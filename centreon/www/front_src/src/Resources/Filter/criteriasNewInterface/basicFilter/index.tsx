import { useStyles } from '../criterias.style';

interface BasicFilter {
  poller: JSX.Element;
  sections: JSX.Element;
  state: JSX.Element;
  types: JSX.Element;
}

const BasicFilter = ({
  sections,
  poller,
  state,
  types
}: BasicFilter): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.basicLayout}>
      <div className={classes.basicMain}>{sections}</div>
      <div className={classes.basicSide}>
        {poller}
        {types}
        {state}
      </div>
    </div>
  );
};

export default BasicFilter;
