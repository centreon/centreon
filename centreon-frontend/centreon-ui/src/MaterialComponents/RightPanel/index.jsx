import React, { useState, useEffect } from 'react';
import PropTypes from 'prop-types';
import { makeStyles, styled } from '@material-ui/core/styles';
import Drawer from '@material-ui/core/Drawer';
import List from '@material-ui/core/List';
import ListItem from '@material-ui/core/ListItem';
import Box from '@material-ui/core/Box';
import ArrowForwardIos from '@material-ui/icons/ArrowForwardIos';

import IconClose from '../Icons/IconClose';
import ExpandableSection from './ExpandableSection';

const PANEL_WIDTH = 560;

const Header = styled(Box)({
  paddingRight: 140,
  paddingLeft: 20,
  boxShadow: '0px 3px 4px 0px rgba(0,0,0,0.15)',
  WebkitBoxShadow: '0px 3px 4px 0px rgba(0,0,0,0.15)',
  MozBoxShadow: '0px 3px 4px 0px rgba(0,0,0,0.15)',
  width: '100%',
  height: 49,
});

const useDrawerStyles = makeStyles({
  modal: {
    pointerEvents: 'none',
  },
  backdrop: {
    backgroundColor: 'transparent',
  },
  paper: {
    top: 52,
    right: 0,
    bottom: 30,
    backgroundColor: '#ededed',
    minWidth: PANEL_WIDTH,
    position: 'absolute',
    pointerEvents: 'all',
  },
});

const Body = styled(Box)({
  height: '100%',
});

const MainPanel = styled(Box)({
  width: 540,
});

const SecondaryPanelBar = styled(Box)({
  border: '1px solid #D6D6D8',
  width: 20,
  height: '100%',
  cursor: 'pointer',
});

const useSecondaryPanelStyles = makeStyles({
  secondaryPanel: {
    width: ({ active }) => (active ? 500 : 0),
    transition: '.3s ease-in-out',
    overflow: 'hidden',
    backgroundColor: '#c7c8c9',
    padding: ({ active }) => (active ? 15 : 0),
  },
});

const ToggleSecondaryPanelIcon = (Icon) =>
  styled(Icon)({
    width: 15,
    margin: 'auto',
  });

const CloseSecondaryPanelIcon = ToggleSecondaryPanelIcon(ArrowForwardIos);

const RightPanel = ({
  active,
  headerComponent,
  secondaryPanelComponent,
  onSecondaryPanelClose,
  sections,
  onClose,
}) => {
  const [secondaryPanelActive, setSecondaryPanelActive] = useState(false);
  const { modal, backdrop, paper } = useDrawerStyles();
  const { secondaryPanel } = useSecondaryPanelStyles({
    active: secondaryPanelActive,
  });

  useEffect(() => {
    setSecondaryPanelActive(secondaryPanelComponent !== undefined);
  }, [secondaryPanelComponent]);

  const toggleSecondaryPanel = () => {
    if (!secondaryPanelComponent) {
      return;
    }
    setSecondaryPanelActive(!secondaryPanelActive);
  };

  const onTransitionEnd = () => {
    if (!secondaryPanelActive) {
      onSecondaryPanelClose();
    }
  };

  const close = () => {
    setSecondaryPanelActive(false);
    onClose();
  };

  return (
    <Drawer
      ModalProps={{ className: modal }}
      BackdropProps={{ className: backdrop, onClick: onClose }}
      PaperProps={{ className: paper }}
      style={{ zIndex: 90 }}
      open={active}
      anchor="right"
    >
      <Header display="flex" flexDirection="row">
        <Box flexGrow={1}>{headerComponent}</Box>
        <Box>
          <IconClose onClick={close} />
        </Box>
      </Header>
      <Body display="flex" flexDirection="row" flexGrow={1}>
        <MainPanel flexGrow={1}>
          <List>
            {sections.map(({ id, title, component, expandable }) =>
              expandable ? (
                <ExpandableSection key={id} title={title}>
                  {component}
                </ExpandableSection>
              ) : (
                <ListItem key={id}>{component}</ListItem>
              ),
            )}
          </List>
        </MainPanel>
        <SecondaryPanelBar
          display="flex"
          alignItems="center"
          alignContent="center"
          onClick={toggleSecondaryPanel}
        >
          {secondaryPanelActive && <CloseSecondaryPanelIcon />}
        </SecondaryPanelBar>
        <div className={secondaryPanel} onTransitionEnd={onTransitionEnd}>
          {secondaryPanelComponent}
        </div>
      </Body>
    </Drawer>
  );
};

RightPanel.defaultProps = {
  onClose: () => {},
  onSecondaryPanelClose: () => {},
  secondaryPanelComponent: undefined,
};

RightPanel.propTypes = {
  active: PropTypes.bool.isRequired,
  headerComponent: PropTypes.node.isRequired,
  secondaryPanelComponent: PropTypes.node,
  sections: PropTypes.arrayOf(PropTypes.shape).isRequired,
  onClose: PropTypes.func,
  onSecondaryPanelClose: PropTypes.func,
};

export default RightPanel;
