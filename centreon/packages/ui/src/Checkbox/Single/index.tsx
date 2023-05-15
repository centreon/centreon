import { makeStyles } from 'tss-react/mui';
import { equals } from 'ramda';

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
        : theme.typography.body1.fontSize
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
            onChange={onChange}
          />
        }
        key={label}
        label={<Typography className={classes.label}>{label}</Typography>}
        labelPlacement={labelPlacement}
      />
    </Box>
  );
};

export default SingleCheckbox;
