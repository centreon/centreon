import { askBeforeCloseFormModalAtom, openFormModalAtom } from './atoms';
import { CrudPageRoot } from './CrudPageRoot';

export const CrudPage = Object.assign(CrudPageRoot, {
  openFormModalAtom,
  askBeforeCloseFormModalAtom
});
