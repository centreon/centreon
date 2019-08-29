/* eslint-disable import/no-extraneous-dependencies */
/* eslint-disable no-undef */

import '@testing-library/react/cleanup-after-each';
import '@testing-library/jest-dom/extend-expect';
import emotionSerializer from 'jest-emotion';

expect.addSnapshotSerializer(emotionSerializer);
