import { Theme } from '@mui/material';

interface Condition {
  color: string;
  condition: (row: Record<string, unknown>) => boolean;
  name: string;
}

const rowColorConditions = (theme: Theme): Array<Condition> => [
  {
    color: theme.palette.action.inDowntimeBackground,
    condition: ({ is_in_downtime }: Record<string, unknown>): boolean =>
      Boolean(is_in_downtime),
    name: 'inDowntime'
  },
  {
    color: theme.palette.action.acknowledgedBackground,
    condition: ({ is_acknowledged }: Record<string, unknown>): boolean =>
      Boolean(is_acknowledged),
    name: 'acknowledged'
  },
  {
    color: theme.palette.action.inFlappingBackground,
    condition: ({ is_in_flapping }: Record<string, unknown>): boolean =>
      Boolean(is_in_flapping),
    name: 'inFlapping'
  }
];

export { rowColorConditions };
