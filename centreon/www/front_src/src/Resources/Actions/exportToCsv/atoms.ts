import { atom } from 'jotai';
import { ColumnId, PageId } from './models';

export const defaultCheckedColumnAtom = atom(ColumnId.allColumns);
export const defaultCheckedPageAtom = atom(PageId.allPages);
