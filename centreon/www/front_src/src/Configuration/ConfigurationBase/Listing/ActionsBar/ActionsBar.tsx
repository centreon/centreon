import Filters from '../../Filters';
import { useActionsStyles } from './Actions.styles';
import AddHostGroups from './AddAction';
import MassiveActions from './MassiveActions/MassiveActions';

const ActionsBar = ({
  hasWriteAccess
}: { hasWriteAccess: boolean }): JSX.Element => {
  const { classes } = useActionsStyles({ hasWriteAccess });

  return (
    <div className={classes.bar}>
      {hasWriteAccess && (
        <div className={classes.actions}>
          <AddHostGroups />
          <MassiveActions />
        </div>
      )}
      <div className={classes.searchBar}>
        <Filters />
      </div>
    </div>
  );
};

export default ActionsBar;
