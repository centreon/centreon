import { useState } from 'react';

import { makeStyles } from 'tss-react/mui';

import ResourceActions from '../../../Actions/Resource';
import { Action, MainActions } from '../../../Actions/model';

const useStyles = makeStyles()({
  container: {
    display: 'flex',
    justifyContent: 'space-between',
    width: '100%'
  }
});

const DetailsActions = ({ details }): JSX.Element => {
  const { classes } = useStyles();
  console.log(details);
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

  const initialize = (): void => {
    setResource([]);
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
    { action: Action.Check, extraRule: null },
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
      displayCondensed={false}
      initialize={initialize}
      mainActions={mainActions as MainActions}
      mainActionsStyle={classes.container}
      resources={resource}
      secondaryActions={[]}
    />
  );
};

export default DetailsActions;
