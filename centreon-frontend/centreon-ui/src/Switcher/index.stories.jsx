import React from 'react';

import Switcher from '.';

export default { title: 'normal' };

export const normal = () => <Switcher />;

export const withTitle = () => <Switcher switcherTitle="Status:" />;

export const withStatus = () => <Switcher switcherStatus="Not Installed" />;
