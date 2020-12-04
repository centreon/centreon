import React from 'react';

import PropTypes from 'prop-types';

import { styled } from '@material-ui/core/styles';

const Wrapper = styled('span')(() => ({
  display: 'inline-block',
  verticalAlign: 'middle',
  height: 24,
  color: '#707070',
}));

const MaterialIcon = ({ children, ...props }) => (
  <Wrapper {...props}>{children}</Wrapper>
);

MaterialIcon.propTypes = {
  children: PropTypes.node.isRequired,
};

export default MaterialIcon;
