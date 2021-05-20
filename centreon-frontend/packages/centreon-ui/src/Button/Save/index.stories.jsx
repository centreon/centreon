import React from 'react';

import ButtonSave from '.';

export default { title: 'Button/Save' };

export const normal = () => <ButtonSave />;

export const loading = () => <ButtonSave loading />;

export const succeeded = () => <ButtonSave succeeded />;
