import { makeStyles } from 'tss-react/mui';

import { SvgIconComponent } from '@mui/icons-material';
import {
  FormControlLabel,
  Checkbox as MuiCheckbox,
  Box,
  Typography
} from '@mui/material';

const useStyles = makeStyles()((theme) => ({
  container: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
    width: 'fit-content'
  },
  icon: {
    color: 'black',
    fontSize: theme.spacing(12),
    fontWeight: '400'
  }
}));

export type LabelPlacement = 'bottom' | 'top' | 'end' | 'start' | undefined;

interface Props {
  Icon?: SvgIconComponent;
  checked: boolean;
  label: string;
  labelPlacement?: LabelPlacement;
  onChange?: () => void;
}

const SingleCheckbox = ({
  Icon,
  checked,
  label,
  onChange,
  labelPlacement = 'end'
}: Props): JSX.Element => {
  const { classes } = useStyles();

  return (
    <Box className={classes.container}>
      {Icon && <Icon className={classes.icon} />}
      <FormControlLabel
        control={
          <MuiCheckbox
            checked={checked}
            color="primary"
            id={label}
            size="small"
            onChange={onChange}
          />
        }
        key={label}
        label={<Typography variant="body2">{label}</Typography>}
        labelPlacement={labelPlacement}
      />
    </Box>
  );
};

export default SingleCheckbox;
