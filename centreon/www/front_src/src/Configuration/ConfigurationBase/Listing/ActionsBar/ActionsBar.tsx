import { JSX } from 'react';
import Filters from '../../Filters';
import { useActionsStyles } from './Actions.styles';
import AddHostGroups from './AddAction';
import MassiveActions from './MassiveActions/MassiveActions';

interface Props {
  hasWriteAccess: boolean;
  hasMassiveActions: boolean;
}

const ActionsBar = ({
  hasWriteAccess,
  hasMassiveActions
}: Props): JSX.Element => {
  const { classes } = useActionsStyles({ hasWriteAccess });

  return (
    <div className={classes.bar}>
      {hasWriteAccess && (
        <div className={classes.actions}>
          <AddHostGroups />
          {hasMassiveActions && <MassiveActions />}
        </div>
      )}
      <div className={classes.searchBar}>
        <Filters />
      </div>
    </div>
  );
};

export default ActionsBar;
