import { PrimitiveAtom } from 'jotai';
import { JSX } from 'react';

import Filters from '../../Filters';
import { useActionsStyles } from './Actions.styles';
import AddHostGroups from './AddAction';
import MassiveActions from './MassiveActions/MassiveActions';

interface Props<TFilters> {
  hasWriteAccess: boolean;
  hasMassiveActions: boolean;
  filtersAtomKey: string;
  filtersAtom: PrimitiveAtom<TFilters>;
}

const ActionsBar = <TFilters,>({
  hasWriteAccess,
  hasMassiveActions,
  filtersAtom,
  filtersAtomKey
}: Props<TFilters>): JSX.Element => {
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
        <Filters<TFilters>
          filtersAtom={filtersAtom}
          filtersAtomKey={filtersAtomKey}
        />
      </div>
    </div>
  );
};

export default ActionsBar;
