import React from 'react';

import PropTypes from 'prop-types';

import { styled } from '@material-ui/core/styles';

const Wrapper = styled('span')(() => ({
  color: '#707070',
  display: 'inline-block',
  height: 24,
  verticalAlign: 'middle',
}));

const MaterialIcon = ({ children, ...props }) => (
  <Wrapper {...props}>{children}</Wrapper>
);

MaterialIcon.propTypes = {
  children: PropTypes.node.isRequired,
};

export default MaterialIcon;
