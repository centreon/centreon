import { makeStyles } from 'tss-react/mui';

// Not Tailwind: the shared `Panel` exposes its surface only through
// `className`, and a v4 utility sits in a cascade layer that an unlayered
// emotion class always beats.
export const usePanelStyles = makeStyles()((theme) => ({
  panel: {
    backgroundColor: theme.palette.background.paper,
    // Only the corners that are visible: the panel is flush right and bottom.
    borderRadius: theme.spacing(1.5, 0, 0, 1.5)
  }
}));
