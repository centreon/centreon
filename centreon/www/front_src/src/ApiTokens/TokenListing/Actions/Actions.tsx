import { useStyles } from './actions.styles';
import Search from './Search';
import TokenFilter from './Filter';

interface Props {
  buttonCreateToken: React.ReactNode;
  refresh: React.ReactNode;
}

const Actions = ({ refresh, buttonCreateToken }: Props): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.container}>
      <div className={classes.subContainerSearch}>
        <Search />
        <TokenFilter />
      </div>
      <div className={classes.subContainer}>
        {buttonCreateToken}
        {refresh}
      </div>
    </div>
  );
};
export default Actions;
