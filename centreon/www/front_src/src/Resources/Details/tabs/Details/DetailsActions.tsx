import { useState } from 'react';

import { makeStyles } from 'tss-react/mui';

import ResourceActions from '../../../Actions/Resource';
import { Action } from '../../../Actions/model';

const useStyles = makeStyles()({
  container: {
    display: 'flex',
    justifyContent: 'space-between'
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

  return (
    <ResourceActions
      displayCondensed={false}
      initialize={initialize}
      mainActions={[
        Action.Acknowledge,
        Action.Downtime,
        Action.Check,
        Action.Disacknowledge
      ]}
      mainActionsStyle={classes.container}
      resources={resource}
      secondaryActions={[]}
    />
  );
};

export default DetailsActions;
