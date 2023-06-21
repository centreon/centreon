import { makeStyles } from 'tss-react/mui';

interface StylesProps {
  tooltipLeftAxisX?: number;
  tooltipLeftAxisYLeft?: number;
  tooltipLeftAxisYRight?: number;
  tooltipTopAxisX?: number;
  tooltipTopAxisYLeft?: number;
  tooltipTopAxisYRight?: number;
}

export const useStyles = makeStyles<StylesProps>()(
  (
    theme,
    {
      tooltipLeftAxisX,
      tooltipTopAxisX,
      tooltipLeftAxisYLeft,
      tooltipTopAxisYLeft,
      tooltipLeftAxisYRight,
      tooltipTopAxisYRight
    }
  ) => ({
    tooltipAxis: {
      '& > span': {
        fontSize: 'inherit',
        fontWeight: 'inherit',
        lineHeight: 'inherit'
      },
      backgroundColor: theme.palette.background.tooltip,
      border: 'unset',
      borderRadius: theme.spacing(0.25),
      color: theme.palette.primary.contrastText,
      fontSize: '10px',
      fontWeight: 500,
      letterSpacing: '0.15px',
      lineHeight: '100%',
      padding: theme.spacing(0.375, 0.5),
      position: 'absolute',
      textAlign: 'center',
      transform: 'translate(-50%, -50%)',
      transformOrigin: 'center center',
      transition: 'unset'
    },
    tooltipAxisBottom: {
      left: tooltipLeftAxisX ?? 0,
      top: tooltipTopAxisX ?? 0
    },
    tooltipAxisLeftY: {
      left: tooltipLeftAxisYLeft ?? 0,
      top: tooltipTopAxisYLeft ?? 0
    },
    tooltipLeftAxisRightY: {
      left: tooltipLeftAxisYRight ?? 0,
      top: tooltipTopAxisYRight ?? 0
    }
  })
);
