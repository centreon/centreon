import React from 'react';

import SearchInput from '.';

export default { title: 'InputField/Search' };

export const normal = (): JSX.Element => <SearchInput />;

export const label = (): JSX.Element => <SearchInput label="Search" />;
