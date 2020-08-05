import * as React from 'react';

import { makeStyles, Paper, Slide } from '@material-ui/core';

const useStyles = makeStyles({
  rightPanel: {
    gridArea: '1 / 2',
    zIndex: 3,
    overflowY: 'auto',
  },
  paperPanel: {
    display: 'grid',
    gridTemplateRows: 'auto 1fr',
    height: '100%',
  },
  slideContent: {
    overflowY: 'auto',
  },
  slideHeader: {
    zIndex: 1,
  },
});

interface SlidePanelProps {
  header: React.ReactElement;
  content: React.ReactElement;
}

const SlidePanel = ({ header, content }: SlidePanelProps): JSX.Element => {
  const classes = useStyles();

  return (
    <Slide
      direction="left"
      in
      timeout={{
        enter: 150,
        exit: 50,
      }}
    >
      <Paper elevation={5} className={classes.rightPanel}>
        <div className={classes.paperPanel}>
          {header && (
            <Paper elevation={3} className={classes.slideHeader}>
              {header}
            </Paper>
          )}
          <div className={classes.slideContent}>{content}</div>
        </div>
      </Paper>
    </Slide>
  );
};

export default SlidePanel;
