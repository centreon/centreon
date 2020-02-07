import React from 'react';
import { styled } from '@material-ui/core/styles';
import PropTypes from 'prop-types';

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
