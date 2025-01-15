import numeral from 'numeral';
import { equals, map, omit, pipe, toPairs } from 'ramda';

import type { Theme } from '@mui/material';

import { StatusType } from './models';

interface Status {
  total: number;
}

interface FormatResponseProps {
  statuses?: StatusType;
  theme: Theme;
}

export interface FormattedResponse {
  color: string;
  label: string;
  value: number;
}

export const formatResponse = ({
  statuses,
  theme
}: FormatResponseProps): Array<FormattedResponse> => {
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

interface ValueByUnitProps {
  total: number;
  unit: 'percentage' | 'number';
  value: number;
}

export const getValueByUnit = ({
  unit,
  value,
  total
}: ValueByUnitProps): string => {
  if (equals(unit, 'number')) {
    return numeral(value).format('0a').toUpperCase();
  }

  return `${((value * 100) / total).toFixed(1)}%`;
};
