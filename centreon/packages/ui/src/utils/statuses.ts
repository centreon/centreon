import type { Theme } from '@mui/material';

export interface StatusColorProps {
  severityCode: SeverityCode;
  theme: Theme;
}

export interface Colors {
  backgroundColor: string;
  color: string;
}

export enum SeverityCode {
  High = 1,
  Medium = 2,
  Low = 3,
  Pending = 4,
  OK = 5,
  None = 6
}

export const getStatusColors = ({
  theme,
  severityCode
}: StatusColorProps): Colors => {
  const { palette } = theme;

  const colorMapping = {
    [SeverityCode.High]: {
      backgroundColor: theme.palette.statusBackground.error,
      color: palette.error.contrastText
    },
    [SeverityCode.Medium]: {
      backgroundColor: theme.palette.statusBackground.warning,
      color: palette.warning.contrastText
    },
    [SeverityCode.Low]: {
      backgroundColor: theme.palette.statusBackground.unknown,
      color: palette.text.primary
    },
    [SeverityCode.Pending]: {
      backgroundColor: theme.palette.statusBackground.pending,
      color: palette.text.primary
    },
    [SeverityCode.OK]: {
      backgroundColor: theme.palette.statusBackground.success,
      color: palette.text.primary
    },
    [SeverityCode.None]: {
      backgroundColor: theme.palette.statusBackground.none,
      color: palette.text.primary
    }
  };

  return colorMapping[severityCode];
};
