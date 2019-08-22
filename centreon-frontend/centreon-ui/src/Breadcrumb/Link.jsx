import React from 'react';
import styled from '@emotion/styled';
import PropTypes from 'prop-types';
import Link from '@material-ui/core/Link';
import { Link as RouterLink } from 'react-router-dom';

const SmallLink = styled(Link)(() => ({
  fontSize: '12px',
}));

const BreadcrumbLink = ({ index, count, breadcrumb }) => {
  const isLastLink = index === count - 1;

  return (
    <SmallLink
      color={isLastLink ? 'textPrimary' : 'inherit'}
      component={RouterLink}
      to={breadcrumb.link}
    >
      {breadcrumb.label}
    </SmallLink>
  );
};

BreadcrumbLink.propTypes = {
  index: PropTypes.number.isRequired,
  count: PropTypes.number.isRequired,
  breadcrumb: PropTypes.shape({
    label: PropTypes.string,
    link: PropTypes.string,
  }).isRequired,
};

export default BreadcrumbLink;
