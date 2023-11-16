import { Theme } from '@mui/material';

import { SeverityCode, getStatusColors } from '@centreon/ui';

interface GetColorProps {
  is_acknowledged?: boolean;
  is_in_downtime?: boolean;
  severityCode?: number;
  theme: Theme;
}

export const getColor = ({
  is_acknowledged,
  is_in_downtime,
  severityCode,
  theme
}: GetColorProps): string => {
  if (is_acknowledged) {
    return theme.palette.action.acknowledgedBackground;
  }
  if (is_in_downtime) {
    return theme.palette.action.inDowntimeBackground;
  }

  return getStatusColors({
    severityCode: severityCode as SeverityCode,
    theme
  }).backgroundColor;
};
