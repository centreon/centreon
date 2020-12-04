import * as React from 'react';

import { isNil } from 'ramda';

import { List, ListItem, makeStyles, Slide, Paper } from '@material-ui/core';
import ForwardIcon from '@material-ui/icons/ArrowForwardIos';

import Panel from '..';
import ContentWithCircularLoading from '../../ContentWithCircularProgress';

import ExpandableSection from './ExpandableSection';

const panelWidth = 550;
const closeSecondaryPanelBarWidth = 20;

const useStyles = makeStyles((theme) => ({
  container: {
    display: (hasSecondaryPanel) => (hasSecondaryPanel ? 'grid' : 'block'),
    gridTemplateColumns: (hasSecondaryPanel) => {
      return hasSecondaryPanel
        ? `1fr ${closeSecondaryPanelBarWidth}px 1fr`
        : '100%';
    },
    height: '100%',
  },
  mainPanel: {
    position: (hasSecondaryPanel) => (hasSecondaryPanel ? 'unset' : 'absolute'),
    bottom: 0,
    left: 0,
    right: 0,
    top: 0,
    overflow: 'auto',
    width: panelWidth,
  },
  closeSecondaryPanelBar: {
    cursor: 'pointer',
    display: 'flex',
    alignItems: 'center',
    alignContent: 'center',
    backgroundColor: theme.palette.background.default,
  },
  closeIcon: {
    width: 15,
    margin: 'auto',
  },
}));

interface Section {
  id: string;
  expandable: boolean;
  title?: string;
  section: JSX.Element;
}

interface Props {
  header: JSX.Element;
  sections: Array<Section>;
  onClose: () => void;
  secondaryPanel?: JSX.Element;
  onSecondaryPanelClose?: () => void;
  loading?: boolean;
}

const SectionPanel = ({
  header,
  secondaryPanel,
  sections,
  onSecondaryPanelClose = () => undefined,
  onClose = () => undefined,
  loading = false,
}: Props): JSX.Element => {
  const hasSecondaryPanel = !isNil(secondaryPanel);

  const classes = useStyles(hasSecondaryPanel);

  const getWidth = (): number => {
    if (hasSecondaryPanel) {
      return panelWidth * 2 + closeSecondaryPanelBarWidth;
    }

    return panelWidth;
  };

  return (
    <Panel
      onClose={onClose}
      header={header}
      width={getWidth()}
      selectedTab={
        <ContentWithCircularLoading alignCenter loading={loading}>
          <div className={classes.container}>
            <List className={classes.mainPanel}>
              {sections.map(({ id, title, section, expandable }) =>
                expandable ? (
                  <ExpandableSection key={id} title={title as string}>
                    {section}
                  </ExpandableSection>
                ) : (
                  <ListItem key={id}>{section}</ListItem>
                ),
              )}
            </List>

            {hasSecondaryPanel && (
              <Paper
                className={classes.closeSecondaryPanelBar}
                aria-label="Close Secondary Panel"
                onClick={onSecondaryPanelClose}
              >
                <ForwardIcon className={classes.closeIcon} color="action" />
              </Paper>
            )}

            <Slide
              in={hasSecondaryPanel}
              direction="left"
              timeout={{ enter: 150, exit: 50 }}
            >
              <div>{secondaryPanel}</div>
            </Slide>
          </div>
        </ContentWithCircularLoading>
      }
    />
  );
};

export default SectionPanel;
