<<<<<<< HEAD
import { Theme } from '@mui/material';
=======
import { Theme } from '@material-ui/core';
>>>>>>> centreon/dev-21.10.x

interface Condition {
  color: string;
  condition;
  name: string;
}

const rowColorConditions = (theme: Theme): Array<Condition> => [
  {
    color: theme.palette.action.inDowntimeBackground,
    condition: ({ in_downtime }): boolean => in_downtime,
    name: 'inDowntime',
  },
  {
    color: theme.palette.action.acknowledgedBackground,
    condition: ({ acknowledged }): boolean => acknowledged,
    name: 'acknowledged',
  },
];

export { rowColorConditions };
