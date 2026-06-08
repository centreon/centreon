import { useTheme } from '@mui/material';

import { getStatusColors, SeverityCode } from '@centreon/ui';

import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

const severityCodeByStatusId: Record<string, SeverityCode> = {
  CRITICAL: SeverityCode.High,
  DOWN: SeverityCode.High,
  OK: SeverityCode.OK,
  PENDING: SeverityCode.Pending,
  UNKNOWN: SeverityCode.Low,
  UNREACHABLE: SeverityCode.Low,
  UP: SeverityCode.OK,
  WARNING: SeverityCode.Medium
};

const useStyles = makeStyles()((theme) => ({
  container: {
    alignItems: 'center',
    display: 'flex',
    flexWrap: 'wrap',
    gap: theme.spacing(0.75)
  },
  toggle: {
    alignItems: 'center',
    background: 'transparent',
    border: `1.5px solid ${theme.palette.divider}`,
    borderRadius: theme.spacing(2),
    color: theme.palette.text.secondary,
    cursor: 'pointer',
    display: 'inline-flex',
    fontFamily: theme.typography.fontFamily,
    fontSize: theme.typography.caption.fontSize,
    fontWeight: theme.typography.fontWeightMedium,
    height: theme.spacing(3.25),
    paddingInline: theme.spacing(1.5)
  },
  toggleActive: {
    border: '1.5px solid transparent'
  }
}));

interface Option {
  id: string;
  name: string;
}

interface Props {
  dataTestId: string;
  onToggle: (option: Option, checked: boolean) => void;
  options: Array<Option>;
  selectedIds: Array<string>;
}

const StatusChipGroup = ({
  options,
  selectedIds,
  onToggle,
  dataTestId
}: Props): JSX.Element => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();
  const theme = useTheme();

  return (
    <div className={classes.container} data-testid={dataTestId}>
      {options.map((option) => {
        const isActive = selectedIds.includes(option.id);
        const severityCode = severityCodeByStatusId[option.id];
        const activeColors = severityCode
          ? getStatusColors({ severityCode, theme })
          : undefined;

        return (
          <button
            aria-pressed={isActive}
            className={cx(classes.toggle, {
              [classes.toggleActive]: isActive
            })}
            data-testid={`${dataTestId}-${option.id}`}
            key={option.id}
            onClick={(): void => onToggle(option, !isActive)}
            style={isActive ? activeColors : undefined}
            type="button"
          >
            {t(option.name)}
          </button>
        );
      })}
    </div>
  );
};

export default StatusChipGroup;
