import { makeStyles } from 'tss-react/mui';

import { SvgIconComponent } from '@mui/icons-material';
import { FormControlLabel, Checkbox as MuiCheckbox, Box } from '@mui/material';

const useStyles = makeStyles()((theme) => ({
  checkbox: { padding: theme.spacing(0.25) },
  container: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
    width: 'fit-content'
  },
  icon: {
    fontSize: theme.spacing(12)
  },
  text: {
    fontSize: theme.spacing(1.25)
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
            className={classes.checkbox}
            color="primary"
            id={label}
            size="small"
            onChange={onChange}
          />
        }
        key={label}
        label={label}
        labelPlacement={labelPlacement}
      />
    </Box>
  );
};

export default SingleCheckbox;
