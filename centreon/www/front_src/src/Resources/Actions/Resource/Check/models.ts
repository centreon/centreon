export interface ClickList {
  onClickCheck: () => void;
  onClickForcedCheck: () => void;
}
export interface Arguments {
  anchorEl: HTMLElement | null;
  isOpen: boolean;
}

export interface Params {
  onClick: () => void;
}

export type SetAtom<Args extends Array<unknown>, Result> = (
  ...args: Args
) => Result;
