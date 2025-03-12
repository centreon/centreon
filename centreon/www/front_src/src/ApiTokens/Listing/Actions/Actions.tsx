import { useActionsStyles } from './Actions.styles';
import Refresh from './Refresh';

import Filters from '../../Filters';
import Add from './Add/Add';

const Actions = (): JSX.Element => {
  const { classes } = useActionsStyles();

  return (
    <div className={classes.container}>
      <div className={classes.actions}>
        <Add />
        <Refresh />
      </div>
      <div className={classes.searchBar}>
        <Filters />
      </div>
    </div>
  );
};
export default Actions;
