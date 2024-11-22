import { Theme } from '@mui/material';

interface Condition {
  color: string;
  condition;
  name: string;
}

const rowColorConditions = (theme: Theme): Array<Condition> => [
  {
    color: theme.palette.action.inDowntimeBackground,
    condition: ({ is_in_downtime }): boolean => is_in_downtime,
    name: 'inDowntime'
  },
  {
    color: theme.palette.action.acknowledgedBackground,
    condition: ({ is_acknowledged }): boolean => is_acknowledged,
    name: 'acknowledged'
  }
];

export { rowColorConditions };
