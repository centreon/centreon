import React from 'react';
import styled from '@emotion/styled';
import PropTypes from 'prop-types';

const Wrapper = styled.span(() => ({
  display: 'inline-block',
  verticalAlign: 'middle',
  height: 24,
  cursor: 'pointer',
  color: '#707070',
}));

function MaterialIcon({ children, ...props }) {
  return <Wrapper {...props}>{children}</Wrapper>;
}

MaterialIcon.propTypes = {
  children: PropTypes.node.isRequired,
};

export default MaterialIcon;
