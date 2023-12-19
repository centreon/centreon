import { useAtom } from 'jotai';

import { useMediaQuery, useTheme } from '@mui/material';

import ResourceActions from './Resource';
import useMediaQueryListing from './Resource/useMediaQueryListing';
import { selectedResourcesAtom } from './actionsAtoms';
import { Action, MainActions, SecondaryActions } from './model';

const WrapperResourceActions = (): JSX.Element => {
  const theme = useTheme();
  const { applyBreakPoint } = useMediaQueryListing();

  const displayCondensed =
    Boolean(useMediaQuery(theme.breakpoints.down(1024))) || applyBreakPoint;

  const [selectedResources, setSelectedResources] = useAtom(
    selectedResourcesAtom
  );
  const initialize = (): void => {
    setSelectedResources([]);
  };

  const mainActions = [
    Action.Acknowledge,
    Action.Disacknowledge,
    Action.Check,
    Action.Downtime
  ];

  const secondaryActions = [
    Action.Comment,
    Action.SubmitStatus,
    Action.Disacknowledge
  ];

  return (
    <ResourceActions
      displayCondensed={displayCondensed}
      initialize={initialize}
      mainActions={mainActions as MainActions}
      resources={selectedResources}
      secondaryActions={secondaryActions as SecondaryActions}
    />
  );
};

export default WrapperResourceActions;
