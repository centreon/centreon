import { useActionsStyles } from './Actions.styles';
import AddHostGroups from './AddAction';
import Filters from './Filters/SearchBar';
import MassiveActions from './MassiveActions';

const ActionsBar = (): JSX.Element => {
  const { classes } = useActionsStyles();

  return (
    <div className={classes.actions}>
      <div className={classes.actions}>
        <AddHostGroups openCreateDialog={() => undefined} />
        <MassiveActions />
      </div>
      <div className={classes.searchBar}>
        <Filters />
      </div>
    </div>
  );
};

export default ActionsBar;
