import React from 'react';

import ButtonSave from '.';

export default { title: 'Button/Save' };

export const normal = (): JSX.Element => <ButtonSave />;

export const loading = (): JSX.Element => <ButtonSave loading />;

export const succeeded = (): JSX.Element => <ButtonSave succeeded />;

export const normalWithText = (): JSX.Element => (
  <ButtonSave labelSave="Save" />
);

export const loadingWithText = (): JSX.Element => (
  <ButtonSave loading labelLoading="Loading" />
);

export const succeededWithText = (): JSX.Element => (
  <ButtonSave succeeded labelSucceeded="Succeeded" />
);

export const normalWithTextAndSmallSize = (): JSX.Element => (
  <ButtonSave labelSave="Save" size="small" />
);

export const loadingWithTextAndSmallSize = (): JSX.Element => (
  <ButtonSave loading labelLoading="Loading" size="small" />
);

export const succeededWithTextAndSmallSize = (): JSX.Element => (
  <ButtonSave succeeded labelSucceeded="Succeeded" size="small" />
);
