import React from 'react';
import styled from '@emotion/styled';
import PropTypes from 'prop-types';
import Breadcrumbs from '@material-ui/core/Breadcrumbs';
import NavigateNextIcon from '@material-ui/icons/NavigateNext';
import BreadcrumbLink from './Link';

const StyledBreadcrumb = styled(Breadcrumbs)(() => ({
  padding: '4px 16px',
  '.MuiBreadcrumbs-li': {
    display: 'flex',
  },
}));

function Breadcrumb({ breadcrumbs }) {
  return (
    <StyledBreadcrumb
      separator={<NavigateNextIcon fontSize="small" />}
      aria-label="Breadcrumb"
    >
      {breadcrumbs &&
        breadcrumbs.map((breadcrumb, index) => (
          <BreadcrumbLink
            key={`${breadcrumb.label}${breadcrumb.index}`}
            breadcrumb={breadcrumb}
            index={index}
            count={breadcrumbs.length}
          />
        ))}
    </StyledBreadcrumb>
  );
}

Breadcrumb.propTypes = {
  breadcrumbs: PropTypes.arrayOf(PropTypes.shape).isRequired,
};

export default Breadcrumb;
