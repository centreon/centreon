import { useState } from 'react';

import { useAtomValue } from 'jotai';
import { lt } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import ResourceActions from '../../../Actions/Resource';
import { Action, MainActions } from '../../../Actions/model';
import {
  labelResourceDetailsCheckCommandSent,
  labelResourceDetailsCheckDescription,
  labelResourceDetailsForcedCheckCommandSent,
  labelResourceDetailsForcedCheckDescription
} from '../../../translatedLabels';
import { panelWidthStorageAtom } from '../../detailsAtoms';

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

  // update details temporary /use decoder after
  const [resource, setResource] = useState([
    {
      ...details,
      has_active_checks_enabled: details?.active_checks,
      has_passive_checks_enabled: details?.passive_checks,
      is_acknowledged: details?.acknowledged,
      is_in_downtime: details?.in_downtime
    }
  ]);

  const panelWidth = useAtomValue(panelWidthStorageAtom);

  const displayCondensed = lt(panelWidth, 615);

  const success = (): void => {};

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
      action: Action.Check,
      data: {
        listOptions: {
          descriptionCheck: labelResourceDetailsCheckDescription,
          descriptionForcedCheck: labelResourceDetailsForcedCheckDescription
        },
        success: {
          msgForcedCheckCommandSent: labelResourceDetailsForcedCheckCommandSent,
          msgLabelCheckCommandSent: labelResourceDetailsCheckCommandSent
        }
      },
      extraRule: null
    },
    {
      action: Action.Disacknowledge,
      extraRules: {
        disabled: !details.acknowledged,
        permitted: details.acknowledged
      }
    }
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
      success={success}
    />
  );
};

export default DetailsActions;
