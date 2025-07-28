import Filters from '../../Filters';
import { useActionsStyles } from './Actions.styles';
import Add from './Add';
import Refresh from './Refresh';

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
