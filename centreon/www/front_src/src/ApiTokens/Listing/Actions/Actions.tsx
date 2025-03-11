import Refresh from './Refresh';
import { useActionsStyles } from './actions.styles';

import Add from './Add/Add';
import Search from './Search';

const Actions = (): JSX.Element => {
  const { classes } = useActionsStyles();

  return (
    <div className={classes.container}>
      <div className={classes.actions}>
        <Add />
        <Refresh />
      </div>
      <div className={classes.searchBar}>
        <Search />
      </div>
    </div>
  );
};
export default Actions;
