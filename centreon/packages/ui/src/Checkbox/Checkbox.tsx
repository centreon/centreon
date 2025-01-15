import { T, always, cond, equals } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { SvgIconComponent } from '@mui/icons-material';
import { Box, FormControlLabel, Checkbox as MuiCheckbox } from '@mui/material';
import Typography, { TypographyProps } from '@mui/material/Typography';

export type LabelPlacement = 'bottom' | 'top' | 'end' | 'start' | undefined;

interface StyleProps {
  hasIcon: boolean;
  labelPlacement: LabelPlacement;
}

const getLabelSpacing = (labelPlacement, theme): string => {
  return cond([
    [equals('top'), always(theme.spacing(0, 0, 0.5))],
    [equals('end'), always(theme.spacing(0, 0, 0, 0.5))],
    [T, always(0)]
  ])(labelPlacement);
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
  labelPlacement?: LabelPlacement;
  labelProps?: TypographyProps;
  onChange?: (e) => void;
}

const Checkbox = ({
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
            id={label}
            size="small"
            sx={{ padding: 0 }}
            onChange={onChange}
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
