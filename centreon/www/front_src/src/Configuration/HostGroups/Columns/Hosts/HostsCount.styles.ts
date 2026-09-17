import { makeStyles } from 'tss-react/mui';

interface StyleProps {
  isHovered: boolean;
}
export const useHostsStyles = makeStyles<StyleProps>()(
  (theme, { isHovered }) => ({
    content: {
      cursor: 'pointer',
      marginLeft: theme.spacing(3)
    },
    hostCount: {
      color: isHovered
        ? theme.palette.text.primary
        : theme.palette.text.secondary
    },
    tooltipContainer: {
      backgroundColor: theme.palette.background.paper,
      boxShadow: '2px 2px 4px rgba(0, 0, 0, 0.2)',
      color: theme.palette.text.primary,
      minHeight: theme.spacing(15),
      padding: 0,
      position: 'relative',
      width: theme.spacing(30)
    }
  })
);

export const useTooltipStyles = makeStyles()((theme) => ({
  body: {
    overflowY: 'auto',
    textAlign: 'start'
  },
  header: {
    alignItems: 'center',
    backgroundColor: theme.palette.common.black,
    color: theme.palette.common.white,
    display: 'flex',
    flexDirection: 'column',
    padding: theme.spacing(1),
    width: '100%'
  }
}));
