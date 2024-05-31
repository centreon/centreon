import { useStyles } from './actions.styles';
import Search from './Search';
import TokenFilter from './Filter';

interface Props {
  buttonCreateToken: React.ReactNode;
  refresh: React.ReactNode;
  width?: number;
}

const Actions = ({
  refresh,
  buttonCreateToken,
  width = 0
}: Props): JSX.Element => {
  const { classes } = useStyles({ width });

  const displaySearch = Boolean(width);

  return (
    <div className={classes.container}>
      <div className={classes.subContainer}>
        {buttonCreateToken}
        {refresh}
      </div>
      {displaySearch && (
        <div className={classes.subContainerSearch}>
          <Search />
          <TokenFilter />
        </div>
      )}
    </div>
  );
};
export default Actions;
