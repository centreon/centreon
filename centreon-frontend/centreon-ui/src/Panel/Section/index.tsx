import * as React from 'react';

import { List, ListItem, Box, makeStyles } from '@material-ui/core';
import ForwardIcon from '@material-ui/icons/ArrowForwardIos';

import ExpandableSection from './ExpandableSection';
import Panel from '..';
import ContentWithCircularLoading from '../../ContentWithCircularProgress';

const panelWidth = 550;
const closeSecondaryPanelBarWidth = 20;

const useStyles = makeStyles((theme) => ({
  container: {
    display: 'grid',
    gridTemplateColumns: `${panelWidth}px ${closeSecondaryPanelBarWidth}px ${panelWidth}px`,
    height: '100%',
  },
  closeSecondaryPanelBar: {
    border: `1px solid ${theme.palette.grey[300]}`,
    borderTop: 0,
    borderBottom: 0,
    cursor: 'pointer',
  },
  closeIcon: {
    width: 15,
    margin: 'auto',
    color: theme.palette.action.disabled,
  },
  secondaryPanel: {
    overflow: 'hidden',
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
  const hasSecondaryPanel = secondaryPanel !== undefined;

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
            <List>
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
              <Box
                className={classes.closeSecondaryPanelBar}
                aria-label="Close Secondary Panel"
                display="flex"
                alignItems="center"
                alignContent="center"
                onClick={onSecondaryPanelClose}
              >
                <ForwardIcon className={classes.closeIcon} />
              </Box>
            )}
            <div className={classes.secondaryPanel}>{secondaryPanel}</div>
          </div>
        </ContentWithCircularLoading>
      }
    />
  );
};

export default SectionPanel;
