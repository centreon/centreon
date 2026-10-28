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
  const { classes } = useActionsStyles();

  return (
    <div className={classes.bar}>
      {hasWriteAccess && (
        <div className={classes.actions}>
          <AddHostGroups />
          {hasMassiveActions && <MassiveActions />}
        </div>
      )}
      {/* Centered whether or not the Add and More actions buttons sit on
          its left, so a read-only user gets the same layout. */}
      <div
        className={`flex w-full items-center justify-center ${hasWriteAccess ? 'px-2' : ''}`}
      >
        <Filters<TFilters>
          filtersAtom={filtersAtom}
          filtersAtomKey={filtersAtomKey}
        />
      </div>
    </div>
  );
};

export default ActionsBar;
