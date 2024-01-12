import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
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
    color: theme.palette.text.secondary,
    paddingLeft: theme.spacing(0.5)
  }
}));

interface StylesProps {
  data: {
    height: number;
    width: number;
  };
}

export const useStatusStyles = makeStyles<StylesProps>()((theme, { data }) => ({
  actions: {
    alignItems: 'center',
    display: 'flex',
    flexWrap: 'nowrap',
    gridGap: theme.spacing(0.25),
    justifyContent: 'center'
  },
  statusColumn: {
    alignItems: 'center',
    display: 'flex',
    width: '100%'
  },
  statusColumnChip: {
    fontWeight: 'bold',
    height: data.height,
    marginLeft: 1,
    minWidth: theme.spacing((data.width - 1) / 8),
    width: '100%'
  }
}));

export default useStyles;
