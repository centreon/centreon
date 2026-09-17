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

  // Typography color for status chips: text action primary (#000000) in light
  // mode, paper (#212121) in dark mode, so it stays readable on the bright
  // status backgrounds in both modes.
  const chipTextColor =
    palette.mode === 'dark' ? palette.background.paper : palette.text.primary;

  const colorMapping = {
    [SeverityCode.High]: {
      backgroundColor: theme.palette.statusBackground.error,
      color: chipTextColor
    },
    [SeverityCode.Medium]: {
      backgroundColor: theme.palette.statusBackground.warning,
      color: chipTextColor
    },
    [SeverityCode.Low]: {
      backgroundColor: theme.palette.statusBackground.unknown,
      color: palette.text.primary
    },
    [SeverityCode.Pending]: {
      backgroundColor: theme.palette.statusBackground.pending,
      color: chipTextColor
    },
    [SeverityCode.OK]: {
      backgroundColor: theme.palette.statusBackground.success,
      color: chipTextColor
    },
    [SeverityCode.None]: {
      backgroundColor: theme.palette.statusBackground.none,
      color: palette.text.primary
    }
  };

  return colorMapping[severityCode];
};
