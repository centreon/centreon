import { makeStyles } from 'tss-react/mui';

// Not a Tailwind utility: it would land in a cascade layer, and this has to be
// the panel's containing block with certainty rather than by luck.
export const usePageStyles = makeStyles()(() => ({
  page: {
    position: 'relative'
  }
}));
