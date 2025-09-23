import { CrudPageRoot } from './CrudPageRoot';
import { askBeforeCloseFormModalAtom, openFormModalAtom } from './atoms';

export const CrudPage = Object.assign(CrudPageRoot, {
  openFormModalAtom,
  askBeforeCloseFormModalAtom
});

export { CrudPageRootProps } from './models';
