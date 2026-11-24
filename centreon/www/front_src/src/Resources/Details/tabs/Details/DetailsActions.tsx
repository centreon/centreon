// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { useSnackbar } from '@centreon/ui';

import { useAtomValue } from 'jotai';
import { lt } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  Action,
  CheckActionModel,
  MainActionModel
} from '../../../Actions/model';
import ResourceActions from '../../../Actions/Resource';
import useAiAvailability from '../../../AiAssistance/useAiAvailability';
import {
  labelResourceDetailsCheckCommandSent,
  labelResourceDetailsCheckDescription,
  labelResourceDetailsForcedCheckCommandSent,
  labelResourceDetailsForcedCheckDescription
} from '../../../translatedLabels';
import { panelWidthStorageAtom } from '../../detailsAtoms';
import { ResourceDetails } from '../../models';
import { checkActionDetailsAtom } from './atoms';
import { useStyles } from './details.styles';

interface Props {
  details: ResourceDetails;
}

// The actions condense below these panel widths: the Explain action needs
// room, so with AI configured they condense sooner.
const condensedPanelWidths = { default: 615, withAi: 720 };

const DetailsActions = ({ details }: Props): JSX.Element => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();

  const { showSuccessMessage } = useSnackbar();

  const panelWidth = useAtomValue(panelWidthStorageAtom);
  const { isConfigured: isAiConfigured } = useAiAvailability();

  const displayCondensed = lt(
    panelWidth,
    isAiConfigured ? condensedPanelWidths.withAi : condensedPanelWidths.default
  );

  const onSuccessCheckAction = (): void => {
    showSuccessMessage(t(labelResourceDetailsCheckCommandSent));
  };

  const onSuccessForcedCheckAction = (): void => {
    showSuccessMessage(t(labelResourceDetailsForcedCheckCommandSent));
  };

  const checkAction: CheckActionModel = {
    action: Action.Check,
    data: {
      listOptions: {
        descriptionCheck: labelResourceDetailsCheckDescription,
        descriptionForcedCheck: labelResourceDetailsForcedCheckDescription
      },
      stateCheckActionAtom: checkActionDetailsAtom,
      successCallback: {
        onSuccessCheckAction,
        onSuccessForcedCheckAction
      }
    },
    extraRules: null
  };

  const mainActions: Array<MainActionModel | CheckActionModel> = [
    {
      action: Action.Acknowledge,
      extraRules: {
        disabled: details?.is_acknowledged,
        permitted: !details?.is_acknowledged
      }
    },
    {
      action: Action.Downtime,
      extraRules: {
        disabled: details?.is_in_downtime,
        permitted: !details?.is_in_downtime
      }
    },
    {
      action: Action.Disacknowledge,
      extraRules: {
        disabled: !details.is_acknowledged,
        permitted: details.is_acknowledged
      }
    },
    checkAction
  ];

  return (
    <ResourceActions
      displayCondensed={displayCondensed}
      mainActions={{
        actions: mainActions,
        style: cx(classes.container, {
          [classes.condensed]: displayCondensed
        })
      }}
      resources={[details]}
      secondaryActions={[]}
    />
  );
};

export default DetailsActions;
