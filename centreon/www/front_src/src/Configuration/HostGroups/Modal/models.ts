export interface DialogState {
  id: number | null;
  isOpen: boolean;
  variant: 'create' | 'update';
}
