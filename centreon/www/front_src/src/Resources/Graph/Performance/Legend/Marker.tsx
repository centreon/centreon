import { equals } from 'ramda';
import { makeStyles } from 'tss-react/mui';

export enum LegendMarkerVariant {
  dot = 0,
  bar = 1
}

interface StylesProps {
  color?: string;
  variant: LegendMarkerVariant;
}

const useStyles = makeStyles<StylesProps>()((theme, { color, variant }) => ({
  disabled: {
    color: theme.palette.text.disabled
  },
  icon: {
    backgroundColor: color,
    borderRadius: equals(LegendMarkerVariant.dot, variant) ? '50%' : 0,
    height: equals(LegendMarkerVariant.dot, variant) ? 9 : '100%',
    marginRight: theme.spacing(0.5),
    width: 9
  }
}));

interface Props {
  color: string;
  disabled?: boolean;
  variant?: LegendMarkerVariant;
}

const LegendMarker = ({
  disabled,
  color,
  variant = LegendMarkerVariant.bar
}: Props): JSX.Element => {
  const { classes, cx } = useStyles({ color, variant });

  return <div className={cx(classes.icon, { [classes.disabled]: disabled })} />;
};

export default LegendMarker;
