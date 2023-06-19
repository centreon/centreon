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
    tooltipAxisBottom: {
      backgroundColor: theme.palette.background.listingHeader,
      color: theme.palette.common.white,
      left: tooltipLeftAxisX ?? 0,
      minWidth: theme.spacing(5.5),
      position: 'absolute',
      textAlign: 'center',
      top: tooltipTopAxisX ?? 0
    },
    tooltipAxisLeftY: {
      backgroundColor: theme.palette.background.listingHeader,
      color: theme.palette.common.white,
      left: tooltipLeftAxisYLeft ?? 0,
      minWidth: theme.spacing(3),
      position: 'absolute',
      textAlign: 'center',
      top: tooltipTopAxisYLeft ?? 0
    },
    tooltipLeftAxisRightY: {
      backgroundColor: theme.palette.background.listingHeader,
      color: theme.palette.common.white,
      left: tooltipLeftAxisYRight ?? 0,
      minWidth: theme.spacing(3),
      position: 'absolute',
      textAlign: 'center',
      top: tooltipTopAxisYRight ?? 0
    }
  })
);
