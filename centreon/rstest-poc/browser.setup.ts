import { afterEach, expect } from '@rstest/core';
import * as matchers from '@testing-library/jest-dom/matchers';
import { cleanup } from '@testing-library/react';

// Real browser: no jsdom polyfills needed (ResizeObserver/IntersectionObserver
// are native). Just wire jest-dom matchers and Testing Library cleanup.
expect.extend(matchers);
afterEach(cleanup);
