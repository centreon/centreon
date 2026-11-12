import { makeStyles } from 'tss-react/mui';

export const useDashboardsPageStyles = makeStyles()((theme) => ({
  headerRow: {
    alignItems: 'center',
    display: 'flex',
    justifyContent: 'space-between'
  },
  titleDescription: {
    color: theme.palette.header.page.description,
    fontSize: '14px',
    marginLeft: '20px'
  },
  titleGroup: {
    alignItems: 'baseline',
    display: 'flex',
    gap: theme.spacing(1.5),
    minWidth: 0
  },
  titleSeparator: {
    alignSelf: 'center',
    borderColor: theme.palette.header.page.border,
    height: '16px'
  },
  titleText: {
    color: theme.palette.header.page.title,
    fontSize: '22px',
    fontWeight: 700,
    whiteSpace: 'nowrap'
  }
}));
