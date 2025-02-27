import { makeStyles } from 'tss-react/mui';

interface StyleProps {
  isHovered: boolean;
}

const useColumnStyles = makeStyles<StyleProps>()((theme, { isHovered }) => ({
  extraSmallChip: {
    height: theme.spacing(1.25),
    lineHeight: theme.spacing(1.25),
    minWidth: theme.spacing(1.25)
  },
  resourceDetailsCell: {
    alignItems: 'center',
    display: 'flex',
    flexWrap: 'nowrap'
  },
  resourceNameItem: {
    lineHeight: 1,
    whiteSpace: 'nowrap'
  },
  resourceNameText: {
    color: isHovered
      ? theme.palette.text.primary
      : theme.palette.text.secondary,
    paddingLeft: theme.spacing(0.5)
  },
  statusChip: {
    marginRight: theme.spacing(0.5)
  }
}));

export default useColumnStyles;
