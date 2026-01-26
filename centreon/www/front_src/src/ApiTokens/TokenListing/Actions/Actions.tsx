import { useStyles } from './actions.styles';
import TokenFilter from './Filters';

interface Props {
  buttonCreateToken: React.ReactNode;
}

const Actions = ({ buttonCreateToken }: Props): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.container}>
      <div className={classes.subContainer}>{buttonCreateToken}</div>

      <div className={classes.subContainerSearch}>
        <TokenFilter />
      </div>
    </div>
  );
};
export default Actions;
