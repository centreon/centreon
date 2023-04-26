import { makeStyles } from 'tss-react/mui';

interface StylesProps {
  panelDynamicAndOpen?: boolean;
}

const useStyles = makeStyles<StylesProps>()((_, { panelDynamicAndOpen }) => ({
  container: {
    display: 'grid',
    gridTemplateColumns: '1fr auto',
    gridTemplateRows: '1fr'
  },
  content: {
    gridArea: panelDynamicAndOpen ? '1 / 1 / 1 / 1' : '1 / 1 / 1 / span 2'
  },
  panel: {
    gridArea: '1 / 2',
    zIndex: 4
  }
}));

interface Props {
  children: JSX.Element;
  fixed?: boolean;
  open?: boolean;
  panel?: JSX.Element;
}

const WithPanel = ({
  children,
  panel,
  open,
  fixed = false
}: Props): JSX.Element => {
  const panelDynamicAndOpen = !!fixed && open;
  const { classes } = useStyles({ panelDynamicAndOpen });

  return (
    <div className={classes.container}>
      <div className={classes.content}>{children}</div>
      {open && <div className={classes.panel}>{panel}</div>}
    </div>
  );
};

export default WithPanel;
