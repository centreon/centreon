import { makeStyles } from 'tss-react/mui';
import { T, always, cond, equals } from 'ramda';

import { SvgIconComponent } from '@mui/icons-material';
import {
  FormControlLabel,
  Checkbox as MuiCheckbox,
  Box,
  Typography
} from '@mui/material';

export type LabelPlacement = 'bottom' | 'top' | 'end' | 'start' | undefined;

interface StyleProps {
  disabled: boolean;
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
  (theme, { disabled, hasIcon, labelPlacement }) => ({
    container: hasIcon
      ? {
          alignItems: 'center',
          display: 'flex',
          flexDirection: 'column'
        }
      : {},
    icon: {
      color: disabled ? theme.palette.grey[400] : theme.palette.primary.main,
      fontSize: theme.spacing(12)
    },
    label: {
      color: disabled ? theme.palette.grey[600] : 'default',
      fontSize: equals(labelPlacement, 'top')
        ? theme.typography.body2.fontSize
        : theme.typography.body1.fontSize,
      padding: getLabelSpacing(labelPlacement, theme)
    }
  })
);

interface Props {
  Icon?: SvgIconComponent;
  checked: boolean;
  disabled?: boolean;
  label: string;
  labelPlacement?: LabelPlacement;
  onChange?: () => void;
}

const SingleCheckbox = ({
  Icon,
  checked,
  label,
  onChange,
  disabled = false,
  labelPlacement = 'end'
}: Props): JSX.Element => {
  const { classes } = useStyles({
    disabled,
    hasIcon: !!Icon,
    labelPlacement
  });

  return (
    <Box className={classes.container}>
      {Icon && <Icon className={classes.icon} />}
      <FormControlLabel
        control={
          <MuiCheckbox
            checked={checked}
            color="primary"
            disabled={disabled}
            id={label}
            size="small"
            sx={{ padding: 0 }}
            onChange={onChange}
          />
        }
        key={label}
        label={<Typography className={classes.label}>{label}</Typography>}
        labelPlacement={labelPlacement}
        sx={{ margin: 0, padding: 0 }}
      />
    </Box>
  );
};

export default SingleCheckbox;
