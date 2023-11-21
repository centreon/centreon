import TokenCreationButton from '../../tokenCreation';

import { useStyles } from './actions.styles';
import Refresh from './refresh';
import Search from './search';
import TokenFilter from './search/filter';

const Actions = (): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.container}>
      <div className={classes.subContainerSearch}>
        <Search />
        <TokenFilter />
      </div>
      <div>
        <TokenCreationButton />
        <Refresh />
      </div>
    </div>
  );
};
export default Actions;
