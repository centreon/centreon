import type { SvgIconComponent } from '@mui/icons-material';
import {
  Box,
  FormControlLabel,
  Checkbox as MuiCheckbox,
  type Theme
} from '@mui/material';
import Typography, { type TypographyProps } from '@mui/material/Typography';

import { makeStyles } from 'tss-react/mui';

export type LabelPlacement = 'bottom' | 'top' | 'end' | 'start' | undefined;

interface StyleProps {
  hasIcon: boolean;
  labelPlacement: LabelPlacement;
}

const getLabelSpacing = (
  labelPlacement: LabelPlacement,
  theme: Theme
): string => {
  if (labelPlacement === 'top') return theme.spacing(0, 0, 0.5);
  if (labelPlacement === 'end') return theme.spacing(0, 0, 0, 0.5);
  return '0';
};

const useStyles = makeStyles<StyleProps>()(
  (theme, { hasIcon, labelPlacement }) => ({
    checkbox: {
      '&.Mui-checked': {
        color: theme.palette.primary.main
      },
      color: theme.palette.primary.main
    },
    container: hasIcon
      ? {
          alignItems: 'center',
          display: 'flex',
          flexDirection: 'column',
          justifyContent: 'space-between',
          minHeight: theme.spacing(11)
        }
      : {},
    icon: {
      fontSize: theme.spacing(10)
    },
    label: {
      fontSize: theme.typography.body2.fontSize,
      fontWeight: theme.typography.fontWeightMedium,
      padding: getLabelSpacing(labelPlacement, theme)
    }
  })
);

interface Props {
  Icon?: SvgIconComponent;
  checked: boolean;
  className?: string;
  dataTestId?: string;
  disabled?: boolean;
  label: string;
  id: string;
  labelPlacement?: LabelPlacement;
  labelProps?: TypographyProps;
  onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
}

const Checkbox = ({
  id,
  Icon,
  checked,
  label,
  onChange,
  className,
  disabled = false,
  labelPlacement = 'end',
  dataTestId,
  labelProps
}: Props): JSX.Element => {
  const { classes, cx } = useStyles({
    hasIcon: !!Icon,
    labelPlacement
  });

  return (
    <Box className={cx(classes.container, className)}>
      {Icon && <Icon className={classes.icon} />}
      <FormControlLabel
        control={
          <MuiCheckbox
            checked={checked}
            className={classes.checkbox}
            color="primary"
            disabled={disabled}
            id={id}
            onChange={onChange}
            size="small"
            sx={{ padding: 0 }}
          />
        }
        data-testid={dataTestId || ''}
        key={label}
        label={
          <Typography classes={{ root: classes.label }} {...labelProps}>
            {label}
          </Typography>
        }
        labelPlacement={labelPlacement}
        sx={{ margin: 0, padding: 0 }}
      />
    </Box>
  );
};

export default Checkbox;
