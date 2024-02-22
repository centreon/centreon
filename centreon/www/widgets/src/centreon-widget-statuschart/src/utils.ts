import { map, omit, pipe, toPairs } from 'ramda';

import type { Theme } from '@mui/material';

import { StatusType } from './models';

interface Status {
  acknowledged: number;
  in_downtime: number;
  total: number;
}

interface FormatResponseProps {
  statuses?: StatusType;
  theme: Theme;
}

export interface FormatedResponse {
  color: string;
  label: string;
  value: number;
}

export const formatResponse = ({
  statuses,
  theme
}: FormatResponseProps): Array<FormatedResponse> => {
  const filteredStatuses = omit(['total'], statuses);

  const result = pipe(
    toPairs,
    map(([label, { total }]: [string, Status]) => ({
      color: getStatusColors({ label, theme }),
      label,
      value: total
    }))
  )(filteredStatuses);

  return result;
};

export interface StatusColorProps {
  label: string;
  theme: Theme;
}

export const getStatusColors = ({ theme, label }: StatusColorProps): string => {
  const colorMapping = {
    acknowledged: theme.palette.action.acknowledgedBackground,
    critical: theme.palette.statusBackground.error,
    down: theme.palette.statusBackground.error,
    in_downtime: theme.palette.action.inDowntimeBackground,
    ok: theme.palette.statusBackground.success,
    pending: theme.palette.statusBackground.pending,
    unknown: theme.palette.statusBackground.unknown,
    unreachable: theme.palette.statusBackground.unknown,
    up: theme.palette.statusBackground.success,
    warning: theme.palette.statusBackground.warning
  };

  return colorMapping[label];
};
