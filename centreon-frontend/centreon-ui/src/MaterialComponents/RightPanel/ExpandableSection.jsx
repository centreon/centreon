import React from 'react';

import ExpansionPanel from '@material-ui/core/ExpansionPanel';
import ExpansionPanelSummary from '@material-ui/core/ExpansionPanelSummary';
import ExpansionPanelDetails from '@material-ui/core/ExpansionPanelDetails';
import ExpandMoreIcon from '@material-ui/icons/ExpandMore';
import { Typography } from '@material-ui/core';
import { styled } from '@material-ui/core/styles';
import ListItem from '@material-ui/core/ListItem';
import PropTypes from 'prop-types';

const Title = styled(Typography)(({ theme }) => ({
  fontSize: theme.typography.pxToRem(15),
  fontWeight: 700,
}));

const Section = styled(ExpansionPanel)({
  width: '100%',
  margin: '0',
  backgroundColor: 'transparent',
  boxShadow: 'none',
  borderBottom: '1px solid #bcbdc0',
  borderRadius: '0',
});

const ExpandableSection = ({ title, children }) => {
  return (
    <Section>
      <ExpansionPanelSummary expandIcon={<ExpandMoreIcon />}>
        <Title>{title}</Title>
      </ExpansionPanelSummary>
      <ExpansionPanelDetails>
        <ListItem>{children}</ListItem>
      </ExpansionPanelDetails>
    </Section>
  );
};

ExpandableSection.defaultProps = {
  title: '',
};

ExpandableSection.propTypes = {
  title: PropTypes.string,
  children: PropTypes.node.isRequired,
};

export default ExpandableSection;
