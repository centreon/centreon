/* eslint-disable react/prop-types */

import React from 'react';
import DynamicComponentPosition from '../DynamicComponentPosition';
import DynamicComponentLoader from '../DynamicComponentLoader';

function DynamicComponentBundle({ topologyUrl, componentName }) {
  return (
    <React.Fragment>
      <DynamicComponentPosition componentName={componentName} />
      <DynamicComponentLoader
        componentName={componentName}
        componentUrl={topologyUrl}
      />
    </React.Fragment>
  );
}

export default DynamicComponentBundle;
