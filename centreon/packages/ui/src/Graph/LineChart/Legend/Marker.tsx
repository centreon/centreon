import { equals } from 'ramda';
import { makeStyles } from 'tss-react/mui';

export enum LegendMarkerVariant {
  'dot',
  'bar'
}

interface StylesProps {
  color?: string;
}

const useStyles = makeStyles<StylesProps>()((theme, { color }) => ({
  disabled: {
    color: theme.palette.text.disabled
  },
  icon: {
    backgroundColor: color,
    borderRadius: theme.shape.borderRadius,
    height: theme.spacing(1.5),
    marginRight: theme.spacing(0.5),
    width: theme.spacing(1.5)
  }
}));

interface Props {
  color: string;
  disabled?: boolean;
}

const LegendMarker = ({ disabled, color }: Props): JSX.Element => {
  const { classes, cx } = useStyles({ color });

  return <div className={cx(classes.icon, { [classes.disabled]: disabled })} />;
};

export default LegendMarker;
