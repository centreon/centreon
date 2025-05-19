import { makeStyles } from 'tss-react/mui';

interface Params {
  singleBarCurrentWidth?: number | string;
}

export const useTopBottomStyles = makeStyles<Params>()(
  (theme, { singleBarCurrentWidth = '100%' } = {}) => ({
    container: {
      display: 'grid',
      gap: theme.spacing(2),
      gridTemplateColumns: 'minmax(50px, 1fr) minmax(100px, 1fr)'
    },
    tooltipContainer: {
      display: 'flex',
      alignItems: 'end',
      height: theme.spacing(6.25),
      marginRight: theme.spacing(3),
      marginTop: theme.spacing(1)
    },
    linkToResourcesStatus: {
      '&:hover': {
        textDecoration: 'underline'
      },
      color: 'inherit',
      textDecoration: 'none'
    },
    loader: {
      display: 'flex',
      flexDirection: 'column',
      gap: theme.spacing(2)
    },
    resourceLabel: {
      cursor: 'pointer',
      overflow: 'hidden',
      textOverflow: 'ellipsis',
      whiteSpace: 'nowrap',
      [theme.containerQueries.up(620)]: {
        maxWidth: '60ch'
      },
      [theme.containerQueries.between(370, 620)]: {
        maxWidth: '40ch'
      },
      [theme.containerQueries.down(370)]: {
        maxWidth: '20ch'
      }
    },
    singleBarContainer: {
      cursor: 'pointer',
      height: theme.spacing(6.25),
      marginTop: theme.spacing(1)
    },
    labelContainer: {
      display: 'flex',
      flexDirection: 'column'
    },
    metricTopContainer: {
      display: 'flex',
      flexDirection: 'column',
      width: singleBarCurrentWidth
    },
    topBottomContainer: {
      display: 'flex',
      flexDirection: 'row',
      containerType: 'inline-size',
      overflow: 'hidden'
    }
  })
);
