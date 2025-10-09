import { JSX } from 'react';
import Filters from '../../Filters';
import { useActionsStyles } from './Actions.styles';
import AddHostGroups from './AddAction';
import MassiveActions from './MassiveActions/MassiveActions';

interface Props {
  hasWriteAccess: boolean;
  hasMassiveActions: boolean;
  filtersAtomKey: string;
  filtersAtom;
}

const ActionsBar = ({
  hasWriteAccess,
  hasMassiveActions,
  filtersAtom,
  filtersAtomKey
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
        <Filters filtersAtom={filtersAtom} filtersAtomKey={filtersAtomKey} />
      </div>
    </div>
  );
};

export default ActionsBar;
