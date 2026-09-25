import { makeStyles } from 'tss-react/mui';

// The shared `Panel` only exposes its surface through `className`, and a
// Tailwind utility cannot win against it: Tailwind v4 emits utilities in a
// cascade layer, which an unlayered emotion class always beats.
export const usePanelStyles = makeStyles()((theme) => ({
  panel: {
    // The mock puts the form itself on the modal white, and keeps the page
    // grey for the header band above it.
    backgroundColor: theme.palette.background.paper,
    // 12px in the mock, and only on the corners that are actually visible:
    // the panel is flush to the right and the bottom of the page. One
    // shorthand, because a longhand next to it loses to whichever emotion
    // serializes last.
    borderRadius: theme.spacing(1.5, 0, 0, 1.5)
  }
}));
