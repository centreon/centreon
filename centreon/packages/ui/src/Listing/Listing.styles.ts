import { makeStyles } from 'tss-react/mui';

import { TableStyleAtom as TableStyle } from './models';

const loadingIndicatorHeight = 3;

interface StylesProps {
  dataStyle: TableStyle;
  getGridTemplateColumn: string;
  isResponsive: string;
  rows: Array<unknown>;
}

const useListingStyles = makeStyles<StylesProps>()(
  (theme, { dataStyle, getGridTemplateColumn, rows, isResponsive }) => ({
    actionBar: {
      alignItems: 'center',
      display: 'flex'
    },
    checkbox: {
      justifyContent: 'start'
    },
    container: {
      background: 'none',
      display: 'flex',
      flexDirection: 'column',
      height: '100%',
      width: '100%'
    },
    listingContainer: {
      height: '100%',
      overflow: 'hidden',
      width: '100%'
    },
    loadingIndicator: {
      height: loadingIndicatorHeight,
      width: '100%'
    },
    table: {
      '.listingHeader': {
        backgroundColor: theme.palette.background.listingHeader,
        padding: theme.spacing(0, 1)
      },
      '.listingHeader > div > div': {
        backgroundColor: theme.palette.background.listingHeader,
        height: theme.spacing(dataStyle.header.height / 8)
      },
      '.listingHeader > div > div:first-of-type': {
        height: '100%',
        padding: theme.spacing(0, 0.5, 0, 1.5)
      },
      alignItems: 'center',
      display: 'grid',
      gridTemplateColumns: getGridTemplateColumn,
      gridTemplateRows: `${theme.spacing(dataStyle.header.height / 8)} repeat(${
        rows?.length || 1
      }, ${isResponsive ? 'auto' : `${dataStyle.body.height}px`})`,
      position: 'relative'
    },
    tableBody: {
      '.MuiTableRow-root > div:first-of-type': {
        paddingLeft: theme.spacing(1.5)
      },

      display: 'contents',
      'div:first-of-type': {
        gridColumnStart: 1
      },
      position: 'relative'
    },
    tableWrapper: {
      borderBottom: 'none',
      overflow: 'auto'
    }
  })
);

export { useListingStyles, loadingIndicatorHeight };
