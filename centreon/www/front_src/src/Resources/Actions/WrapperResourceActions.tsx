import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { useSnackbar } from '@centreon/ui';

import {
  labelCheckCommandSent,
  labelCheckDescription,
  labelForcedCheckCommandSent,
  labelForcedCheckDescription
} from '../translatedLabels';

import ResourceActions from './Resource';
import { selectedResourcesAtom } from './actionsAtoms';
import {
  Action,
  CheckActionModel,
  MainActionModel,
  MoreSecondaryActions,
  SecondaryActions
} from './model';

interface Props {
  displayCondensed?: boolean;
}

const WrapperResourceActions = ({
  displayCondensed = false,
  renderMoreSecondaryActions
}: Props & MoreSecondaryActions): JSX.Element => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

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
      renderMoreSecondaryActions={renderMoreSecondaryActions}
      resources={selectedResources}
      secondaryActions={secondaryActions as SecondaryActions}
      success={initialize}
    />
  );
};

export default WrapperResourceActions;
