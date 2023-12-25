import { useAtomValue } from 'jotai';
import { lt } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { useSnackbar } from '@centreon/ui';

import ResourceActions from '../../../Actions/Resource';
import { Action, CheckActionModel, MainActions } from '../../../Actions/model';
import {
  labelResourceDetailsCheckCommandSent,
  labelResourceDetailsCheckDescription,
  labelResourceDetailsForcedCheckCommandSent,
  labelResourceDetailsForcedCheckDescription
} from '../../../translatedLabels';
import { panelWidthStorageAtom } from '../../detailsAtoms';

import { checkActionDetailsAtom } from './atoms';

const useStyles = makeStyles()((theme) => ({
  condensed: {
    justifyContent: 'space-evenly'
  },
  container: {
    display: 'flex',
    justifyContent: 'space-between',
    marginBottom: theme.spacing(2),
    width: '100%'
  }
}));

const DetailsActions = ({ details }): JSX.Element => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();

  const { showSuccessMessage } = useSnackbar();

  // update details temporary /use decoder after
  const resource = [
    {
      ...details,
      has_active_checks_enabled: details?.active_checks,
      has_passive_checks_enabled: details?.passive_checks,
      is_acknowledged: details?.acknowledged,
      is_in_downtime: details?.in_downtime
    }
  ];

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
      checkActionStateAtom: checkActionDetailsAtom,
      listOptions: {
        descriptionCheck: labelResourceDetailsCheckDescription,
        descriptionForcedCheck: labelResourceDetailsForcedCheckDescription
      },
      successCallback: {
        onSuccessCheckAction,
        onSuccessForcedCheckAction
      }
    },
    extraRules: null
  };

  const mainActions = [
    {
      action: Action.Acknowledge,
      extraRules: {
        disabled: details?.acknowledged,
        permitted: !details?.acknowledged
      }
    },
    {
      action: Action.Downtime,
      extraRules: {
        disabled: details?.in_downtime,
        permitted: !details?.in_downtime
      }
    },
    {
      action: Action.Disacknowledge,
      extraRules: {
        disabled: !details.acknowledged,
        permitted: details.acknowledged
      }
    },
    checkAction
  ];

  return (
    <ResourceActions
      displayCondensed={displayCondensed}
      mainActions={mainActions as MainActions}
      mainActionsStyle={cx(classes.container, {
        [classes.condensed]: displayCondensed
      })}
      resources={resource}
      secondaryActions={[]}
    />
  );
};

export default DetailsActions;
