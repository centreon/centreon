import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { useMediaQuery, useTheme } from '@mui/material';

import { useSnackbar } from '@centreon/ui';

import {
  labelCheckCommandSent,
  labelCheckDescription,
  labelForcedCheckCommandSent,
  labelForcedCheckDescription
} from '../translatedLabels';

import ResourceActions from './Resource';
import useMediaQueryListing from './Resource/useMediaQueryListing';
import { selectedResourcesAtom } from './actionsAtoms';
import {
  Action,
  CheckActionModel,
  MainActionModel,
  SecondaryActions
} from './model';

const WrapperResourceActions = (): JSX.Element => {
  const theme = useTheme();
  const { t } = useTranslation();
  const { applyBreakPoint } = useMediaQueryListing();
  const { showSuccessMessage } = useSnackbar();

  const displayCondensed =
    Boolean(useMediaQuery(theme.breakpoints.down(1024))) || applyBreakPoint;

  const [selectedResources, setSelectedResources] = useAtom(
    selectedResourcesAtom
  );
  const initialize = (): void => {
    setSelectedResources([]);
  };

  const onSuccessCheckAction = (): void => {
    showSuccessMessage(t(labelCheckCommandSent));
  };

  const onSuccessForcedCheckAction = (): void => {
    showSuccessMessage(t(labelForcedCheckCommandSent));
  };

  const checkAction: CheckActionModel = {
    action: Action.Check,
    data: {
      listOptions: {
        descriptionCheck: labelCheckDescription,
        descriptionForcedCheck: labelForcedCheckDescription
      },

      successCallback: {
        onSuccessCheckAction,
        onSuccessForcedCheckAction
      }
    },
    extraRules: null
  };

  const mainActions: Array<MainActionModel | CheckActionModel> = [
    { action: Action.Acknowledge, extraRules: null },
    checkAction,
    { action: Action.Downtime, extraRules: null }
  ];

  const secondaryActions = [
    { action: Action.Comment, extraRules: null },
    { action: Action.SubmitStatus, extraRules: null },
    { action: Action.Disacknowledge, extraRules: null }
  ];

  return (
    <ResourceActions
      displayCondensed={displayCondensed}
      mainActions={{ actions: mainActions }}
      resources={selectedResources}
      secondaryActions={secondaryActions as SecondaryActions}
      success={initialize}
    />
  );
};

export default WrapperResourceActions;
