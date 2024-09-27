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

export const useSeverityStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex'
  },
  firstColumn: {
    display: 'flex',
    minWidth: theme.spacing(5)
  },
  root: {
    display: 'flex',
    flexDirection: 'column'
  },
  rowContainer: {
    alignItems: 'center',
    display: 'flex',
    flexWrap: 'wrap'
  },
  text: {
    display: 'flex'
  }
}));

export const useTypeChipStyles = makeStyles()((theme) => ({
  containerLabel: {
    padding: theme.spacing(0.5)
  },
  label: {
    alignItems: 'center',
    justifyContent: 'center',
    lineHeight: 1
  }
}));

export const useHoverChiptStyles = makeStyles()(() => ({
  iconButton: {
    padding: 0
  },
  tooltip: {
    backgroundColor: 'transparent',
    maxWidth: 'none'
  }
}));

export const useStateStyles = makeStyles()({
  container: {
    display: 'flex',
    flexDirection: 'row',
    gridGap: 2,
    marginLeft: 2
  }
});

export const useOpenTicketStyles = makeStyles()((theme) => ({
  actions: {
    alignItems: 'center',
    display: 'flex',
    flexWrap: 'nowrap',
    gridGap: theme.spacing(0.25),
    justifyContent: 'center'
  },
  tooltip: {
    backgroundColor: theme.palette.background.paper,
    color: theme.palette.text.primary,
    padding: 0,
    position: 'relative'
  },
  iconWithBadge: {
    padding: '1px 1px',
    justifyContent: 'inherit',
    alignContent: 'inherit',
    alignItems: 'inherit'
  }
}));

export default useStyles;
