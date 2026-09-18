import { makeStyles } from 'tss-react/mui';

export const useSortableSingleConnectedAutocompleteListStyles = makeStyles()(
  (theme) => ({
    actions: {
      alignItems: 'center',
      display: 'flex',
      flex: '0 0 auto',
      gap: theme.spacing(0.5)
    },
    dragHandle: {
      cursor: 'grab'
    },
    row: {
      alignItems: 'center',
      display: 'flex',
      gap: theme.spacing(1),
      width: '100%'
    },
    selector: {
      flex: '1 1 auto',
      minWidth: 0
    }
  })
);
