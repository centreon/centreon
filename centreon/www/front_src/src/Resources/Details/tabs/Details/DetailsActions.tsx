import { useAtomValue } from 'jotai';
import { lt } from 'ramda';
import { useTranslation } from 'react-i18next';

import { useSnackbar } from '@centreon/ui';

import ResourceActions from '../../../Actions/Resource';
import {
  Action,
  CheckActionModel,
  MainActionModel
} from '../../../Actions/model';
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

const DetailsActions = ({ details }: Props): JSX.Element => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();

  const { showSuccessMessage } = useSnackbar();

  const panelWidth = useAtomValue(panelWidthStorageAtom);

  const displayCondensed = lt(panelWidth, 615);

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
