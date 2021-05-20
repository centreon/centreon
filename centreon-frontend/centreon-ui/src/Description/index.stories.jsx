import React from 'react';

import Description from '.';

export default { title: 'Description' };

export const contentDate = () => (
  <Description date="Description content date 12/7/2018" />
);

export const contentTitle = () => (
  <Description title="Description content title" />
);

export const contentText = () => (
  <Description text="Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum." />
);

export const contentNote = () => (
  <Description note="Release note of v 3.11.5 available here >" />
);
