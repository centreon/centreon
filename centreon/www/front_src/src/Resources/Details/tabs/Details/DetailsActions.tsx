import { useState } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { always, ifElse, lt, pathEq, pathOr } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { getData, useRequest } from '@centreon/ui';

import ResourceActions from '../../../Actions/Resource';
import { Action, MainActions } from '../../../Actions/model';
import {
  labelNoResourceFound,
  labelResourceDetailsCheckCommandSent,
  labelResourceDetailsCheckDescription,
  labelResourceDetailsForcedCheckCommandSent,
  labelResourceDetailsForcedCheckDescription,
  labelSomethingWentWrong
} from '../../../translatedLabels';
import {
  detailsAtom,
  panelWidthStorageAtom,
  selectedResourceDetailsEndpointDerivedAtom
} from '../../detailsAtoms';
import { ResourceDetails } from '../../models';

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

  const { sendRequest: sendLoadDetailsRequest } = useRequest<ResourceDetails>({
    getErrorMessage: ifElse(
      pathEq(404, ['response', 'status']),
      always(t(labelNoResourceFound)),
      pathOr(t(labelSomethingWentWrong), ['response', 'data', 'message'])
    ),
    request: getData
  });

  const panelWidth = useAtomValue(panelWidthStorageAtom);

  const selectedResourceDetailsEndpoint = useAtomValue(
    selectedResourceDetailsEndpointDerivedAtom
  );
  const setDetails = useSetAtom(detailsAtom);
  const displayCondensed = lt(panelWidth, 615);

  const success = (): void => {
    setTimeout(() => {
      sendLoadDetailsRequest({
        endpoint: selectedResourceDetailsEndpoint
      }).then((data) => {
        setDetails(data);
      });
    }, 10000);
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
