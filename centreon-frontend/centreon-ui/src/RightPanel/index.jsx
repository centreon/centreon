import React, { useState, useEffect } from 'react';
import PropTypes from 'prop-types';
import { makeStyles, styled } from '@material-ui/core/styles';
import List from '@material-ui/core/List';
import ListItem from '@material-ui/core/ListItem';
import Box from '@material-ui/core/Box';
import ArrowForwardIos from '@material-ui/icons/ArrowForwardIos';
import Slide from '@material-ui/core/Slide';

import IconClose from '../Icon/IconClose2';
import Loader from '../Loader';
import ExpandableSection from './ExpandableSection';

const panelWidth = 560;
const inAnimationDurationMs = 150;
const outAnimationDurationMs = 50;

// TODO this should be dynamic
const topHeight = 52;

const HeaderContainer = styled(Box)({
  paddingLeft: 20,
  boxShadow: '0px 3px 4px 0px rgba(0,0,0,0.15)',
  WebkitBoxShadow: '0px 3px 4px 0px rgba(0,0,0,0.15)',
  MozBoxShadow: '0px 3px 4px 0px rgba(0,0,0,0.15)',
  height: 49,
});

const BodyContainer = styled('div')({
  top: topHeight,
  right: 0,
  bottom: 30,
  backgroundColor: '#ededed',
  minWidth: panelWidth,
  position: 'absolute',
  pointerEvents: 'all',
  outline: 'none',
  boxShadow:
    '0px 8px 10px -5px rgba(0,0,0,0.2), 0px 16px 24px 2px rgba(0,0,0,0.14), 0px 6px 30px 5px rgba(0,0,0,0.12)',
  WebkitBoxShadow:
    '0px 8px 10px -5px rgba(0,0,0,0.2), 0px 16px 24px 2px rgba(0,0,0,0.14), 0px 6px 30px 5px rgba(0,0,0,0.12)',
  MozBoxShadow:
    '0px 8px 10px -5px rgba(0,0,0,0.2), 0px 16px 24px 2px rgba(0,0,0,0.14), 0px 6px 30px 5px rgba(0,0,0,0.12)',
  zIndex: 90,
});

const Body = styled(Box)({
  height: '100%',
});

const MainPanel = styled(Box)({
  width: 540,
  overflowY: 'auto',
  marginBottom: topHeight,
});

const SecondaryPanelBar = styled(Box)({
  border: '1px solid #D6D6D8',
  width: 20,
  cursor: 'pointer',
  marginBottom: topHeight,
});

const useSecondaryPanelStyles = makeStyles({
  secondaryPanel: {
    width: ({ active }) => (active ? 500 : 0),
    transition: '.1s ease-in-out',
    overflow: 'hidden',
    backgroundColor: '#c7c8c9',
    padding: ({ active }) => (active ? 5 : 0),
  },
});

const CloseSecondaryPanelIcon = styled(ArrowForwardIos)({
  width: 15,
  margin: 'auto',
});

const RightPanel = ({
  active,
  Header,
  secondaryPanelComponent,
  onSecondaryPanelClose,
  Sections,
  onClose,
  onOpen,
  loading,
}) => {
  const [secondaryPanelActive, setSecondaryPanelActive] = useState(false);
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
    <Slide
      in={active}
      direction="left"
      onEntered={onOpen}
      timeout={{
        enter: inAnimationDurationMs,
        exit: outAnimationDurationMs,
      }}
    >
      <BodyContainer>
        {loading && <Loader fullContent />}
        <HeaderContainer display="flex" flexDirection="row">
          <Box flexGrow={1}>{Header}</Box>
          <Box>
            <IconClose
              onClick={close}
              style={{ width: 39, height: 39, padding: 5 }}
            />
          </Box>
        </HeaderContainer>
        <Body display="flex" flexDirection="row" flexGrow={1}>
          <MainPanel flexGrow={1}>
            <List>
              {Sections.map(({ id, title, Section, expandable }) =>
                expandable ? (
                  <ExpandableSection key={id} title={title}>
                    {Section}
                  </ExpandableSection>
                ) : (
                  <ListItem key={id}>{Section}</ListItem>
                ),
              )}
            </List>
          </MainPanel>
          <SecondaryPanelBar
            aria-label="Close Secondary Panel"
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
      </BodyContainer>
    </Slide>
  );
};

RightPanel.defaultProps = {
  onClose: () => {},
  onOpen: () => {},
  onSecondaryPanelClose: () => {},
  secondaryPanelComponent: undefined,
  loading: false,
};

RightPanel.propTypes = {
  active: PropTypes.bool.isRequired,
  loading: PropTypes.bool,
  Header: PropTypes.node.isRequired,
  secondaryPanelComponent: PropTypes.node,
  Sections: PropTypes.arrayOf(PropTypes.shape).isRequired,
  onClose: PropTypes.func,
  onOpen: PropTypes.func,
  onSecondaryPanelClose: PropTypes.func,
};

export default RightPanel;
